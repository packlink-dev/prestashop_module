<?php

namespace Packlink\PrestaShop\Classes\BusinessLogicServices;

use Address as PrestaShopAddress;
use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Logeecom\Infrastructure\ServiceRegister;
use Order as PrestaShopOrder;
use Packlink\BusinessLogic\Configuration;
use Packlink\BusinessLogic\Http\DTO\Shipment;
use Packlink\BusinessLogic\Http\DTO\Tracking;
use Packlink\BusinessLogic\Order\Exceptions\OrderNotFound;
use Packlink\BusinessLogic\Order\Objects\Address;
use Packlink\BusinessLogic\Order\Objects\Item;
use Packlink\BusinessLogic\Order\Objects\Order;
use Packlink\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Logeecom\Infrastructure\ORM\QueryFilter\Operators;
use Packlink\PrestaShop\Classes\Entities\CartCarrierDropOffMapping;
use Packlink\PrestaShop\Classes\Entities\CartDdpSelection;
use Packlink\PrestaShop\Classes\Repositories\OrderRepository;
use Packlink\PrestaShop\Classes\Utility\CustomsDataProvider;
use Packlink\PrestaShop\Classes\Utility\CustomsInvoiceSynchronizer;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

/**
 * Class ShopOrderService
 *
 * @package Packlink\PrestaShop\Classes\BusinessLogicServices
 */
class ShopOrderService implements \Packlink\BusinessLogic\Order\Interfaces\ShopOrderService
{
    /**
     * Resolves the customs values (tariff number, country of origin, receiver tax id) for the current
     * order build. Shared with the checkout duty-estimate path so both read identical data.
     *
     * @var CustomsDataProvider|null
     */
    private $customsDataProvider;
    /**
     * Per-request cache of loaded PrestaShop delivery addresses, keyed by address id, so the same
     * address is not hydrated more than once during an order build.
     *
     * @var PrestaShopAddress[]
     */
    private $deliveryAddressCache = array();
    /**
     * The stored customs mapping for the current order build (which PrestaShop source feeds each
     * customs field), or null when none is configured.
     *
     * @var \Packlink\BusinessLogic\Customs\Models\CustomsMapping|null
     */
    private $customsMapping;

    /**
     * Handles updated tracking info for order with a given ID.
     *
     * @param string $orderId Shop order ID.
     * @param Shipment $shipment Shipment object containing tracking codes and tracking url.
     * @param Tracking[] $trackingHistory Shipment tracking history.
     *
     * @throws \Packlink\BusinessLogic\Order\Exceptions\OrderNotFound
     */
    public function updateTrackingInfo($orderId, Shipment $shipment, array $trackingHistory)
    {
        if (!empty($shipment->trackingCodes)) {
            $repository = new OrderRepository();
            $repository->setTrackingNumber((int)$orderId, $shipment->trackingCodes[0]);
        }

        // A customs invoice created or replaced in the Packlink UI - which is what happens when the
        // draft could not be completed from the shop and the merchant finished it there - carries an id
        // the module never saw, leaving the order page with no Customs row. Pick up whichever invoice
        // the shipment currently points at, i.e. the last one created for it.
        if (!empty($shipment->reference)) {
            CustomsInvoiceSynchronizer::sync($shipment->reference);
        }
    }

    /**
     * Sets order Packlink shipping status to an order with a given ID.
     *
     * @param string $orderId Shop order ID.
     * @param string $shippingStatus Packlink shipping status.
     *
     * @throws \Packlink\BusinessLogic\Order\Exceptions\OrderNotFound When order for provided reference is not found.
     */
    public function updateShipmentStatus($orderId, $shippingStatus)
    {
        $repository = new OrderRepository();

        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        $statusMappings = $configService->getOrderStatusMappings();

        if (array_key_exists($shippingStatus, $statusMappings)) {
            $repository->updateOrderState((int)$orderId, (int)$statusMappings[$shippingStatus]);
        } else {
            Logger::logWarning(TranslationUtility::__('Order status mapping not found.'), 'Integration');
        }
    }

    /**
     * Fetches and returns system order by its unique identifier.
     *
     * @param string $orderId $orderId Unique order id.
     *
     * @return Order Order object.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function getOrderAndShippingData($orderId)
    {
        $order = new Order();
        try {
            $sourceOrder = $this->getOrder($orderId);
            $currencyId = (int)$sourceOrder->id_currency;
            $currency = \Currency::getCurrency($currencyId);

            $reference = !empty($sourceOrder->reference) ? $sourceOrder->reference : (string)$orderId;
            $order->setId($reference);
            $order->setOrderNumber($reference);
            $order->setCustomerId((int)$sourceOrder->id_customer);
            $order->setCurrency($currency['iso_code']);
            $order->setTotalPrice((float)$sourceOrder->total_paid_tax_incl);
            $order->setBasePrice((float)$sourceOrder->total_paid_tax_excl);
            $dropOffId = $this->getDropOffId($sourceOrder);
            if ($dropOffId) {
                $order->setShippingDropOffId($dropOffId);
            }

            if ($sourceOrder) {
                $order->setPaymentId($sourceOrder->module);
            }

            $order->setShippingAddress($this->getAddress($sourceOrder));

            // Ensure the customs defaults are derived from real shipment data before the invoice is
            // built: country of origin from the default warehouse (where the goods ship from) and the
            // sender tax id from the connected Packlink account. Without these, international customs
            // invoices are rejected by Packlink (blank country_of_origin / sender tax_id) and silently
            // skipped, so no customs document is ever generated.
            $this->ensureCustomsDefaults();

            // Customs receiver data. Honor the merchant's data-mapping selections (which PrestaShop
            // source feeds each customs field); the core invoice build falls back to the configured
            // mapping defaults when a value is absent.
            $this->customsMapping = $this->loadCustomsMapping();
            $this->customsDataProvider = new CustomsDataProvider($this->customsMapping);

            $receiverTaxId = $this->resolveReceiverTaxId($sourceOrder);
            if ($receiverTaxId !== '') {
                $order->setTaxId($receiverTaxId);
            }
            $companyVat = $this->resolveCompanyVat($sourceOrder);
            if ($companyVat !== '') {
                $order->setVatNumber($companyVat);
            }

            $this->setOrderShippingDetails($order, $sourceOrder->id_carrier);
            $items = $this->getOrderItems($sourceOrder);
            $this->applyDdpSelection($order, $sourceOrder);

            $order->setItems($items);
            // Customs: the customs-invoice request sends order-level parcels weight,
            // which Packlink rejects at 0 (causing the shipment to be sent without customs and the
            // carrier to reject international shipments). Populate it from the built items.
            $order->setTotalWeight($this->calculateTotalWeight($items));
        } catch (OrderNotFound $e) {
            Logger::logWarning(TranslationUtility::__('Source order not found'), 'Integration');
        }

        return $order;
    }

    /**
     * Retrieves drop-off id if shop order has drop off shipping service selected.
     *
     * Returns null otherwise.
     *
     * @param PrestaShopOrder $shopOrder
     *
     * @return string | null
     */
    private function getDropOffId(PrestaShopOrder $shopOrder)
    {
        try {
            $repository = RepositoryRegistry::getRepository(
                CartCarrierDropOffMapping::getClassName()
            );

            $query = new QueryFilter();
            $query->where('cartId', '=', (string)$shopOrder->id_cart)
                ->where('carrierReferenceId', '=', (string)$shopOrder->id_carrier);

            /** @var \Packlink\PrestaShop\Classes\Entities\CartCarrierDropOffMapping $mapping */
            $mapping = $repository->selectOne($query);
            if ($mapping) {
                $dropOff = $mapping->getDropOff();

                return (string)$dropOff['id'];
            }
        } catch (\Exception $e) {
            Logger::logWarning(
                'Error when fetching the drop-off information. Error: ' . $e->getMessage(),
                'Integration'
            );
        }

        return null;
    }

    /**
     * Returns packlink address from shop address.
     *
     * @param PrestaShopOrder $shopOrder
     *
     * @return \Packlink\BusinessLogic\Order\Objects\Address
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function getAddress(PrestaShopOrder $shopOrder)
    {
        $deliveryAddressId = (int)$shopOrder->id_address_delivery;

        $shippingAddress = new Address();
        $deliveryAddress = $this->loadDeliveryAddress($deliveryAddressId);
        $country = new \Country($deliveryAddress->id_country);

        if (\Validate::isLoadedObject($country)) {
            $shippingAddress->setCountry($country->iso_code);
        }

        $shippingAddress->setZipCode($deliveryAddress->postcode);
        $shippingAddress->setCity($deliveryAddress->city);
        $shippingAddress->setCompany($deliveryAddress->company);
        $shippingAddress->setPhone($deliveryAddress->phone ?: $deliveryAddress->phone_mobile);
        $shippingAddress->setStreet1($deliveryAddress->address1);
        $shippingAddress->setStreet2($deliveryAddress->address2);
        $shippingAddress->setName($deliveryAddress->firstname);
        $shippingAddress->setSurname($deliveryAddress->lastname);

        $customer = new \Customer($shopOrder->id_customer);
        if (\Validate::isLoadedObject($customer)) {
            $shippingAddress->setEmail($customer->email);
            $shippingAddress->setName($deliveryAddress->firstname ?: $customer->firstname);
            $shippingAddress->setSurname($deliveryAddress->lastname ?: $customer->lastname);
        }

        return $shippingAddress;
    }

    /**
     * Sets order shipping details.
     *
     * @param Order $order Packlink order object.
     * @param int $carrierId ID of PrestaShop carrier.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    private function setOrderShippingDetails($order, $carrierId)
    {
        /** @var CarrierService $carrierService */
        $carrierService = ServiceRegister::getService(ShopShippingMethodService::CLASS_NAME);
        $carrier = new \Carrier($carrierId);

        if (!\Validate::isLoadedObject($carrier)) {
            Logger::logWarning(TranslationUtility::__('Carrier not found'), 'Integration');

            return;
        }

        $shippingMethodId = $carrierService->getShippingMethodId((int)$carrier->id_reference);
        if ($shippingMethodId !== null) {
            $order->setShippingMethodId($shippingMethodId);
        } else {
            Logger::logWarning(TranslationUtility::__('Carrier service mapping not found'), 'Integration');
        }
    }

    /**
     * Sets order items that belong to provided order.
     *
     * @param PrestaShopOrder $sourceOrder PrestaShop order object.
     *
     * @return Item[] An array of order items.
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function getOrderItems(PrestaShopOrder $sourceOrder)
    {
        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        $defaultParcel = $configService->getDefaultParcel();

        $sourceOrderItems = $sourceOrder->getOrderDetailList();

        $productIds = array();
        foreach ($sourceOrderItems as $sourceOrderItem) {
            $productIds[] = (int)$sourceOrderItem['product_id'];
        }
        $this->preloadProductCustomsData($productIds);

        $orderItems = array();
        /** @var array $sourceOrderItem */
        foreach ($sourceOrderItems as $sourceOrderItem) {
            $product = new \Product((int)$sourceOrderItem['product_id']);
            if (!$product->is_virtual) {
                $orderItem = $this->getOrderItem($sourceOrderItem, $defaultParcel);

                $orderItem->setPrice((float)$sourceOrderItem['unit_price_tax_excl']);
                $orderItem->setTotalPrice((float)$sourceOrderItem['unit_price_tax_incl']);

                $orderItems[] = $orderItem;
            }
        }

        return $orderItems;
    }

    /**
     * Sums the total shipment weight from the built order items (weight x quantity).     *
     * @param \Packlink\BusinessLogic\Order\Objects\Item[] $items
     *
     * @return float
     */
    private function calculateTotalWeight(array $items)
    {
        $total = 0;
        foreach ($items as $item) {
            $total += (float)$item->getWeight() * (int)$item->getQuantity();
        }

        return round($total, 2);
    }

    /**
     * Sets additional order item information (title, quantity, category...).
     *
     * @param array $sourceOrderItem PrestaShop order item.
     *
     * @param \Packlink\BusinessLogic\Http\DTO\ParcelInfo $defaultParcel
     *
     * @return \Packlink\BusinessLogic\Order\Objects\Item
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function getOrderItem($sourceOrderItem, $defaultParcel)
    {
        $orderItem = new Item();
        $product = new \Product((int)$sourceOrderItem['product_id']);
        $languageId = (int)\Context::getContext()->language->id;


        $orderItem->setQuantity((int)$sourceOrderItem['product_quantity']);

        if (!empty($product->name)) {
            $orderItem->setTitle($product->name[$languageId]);
        }

        $category = new \Category((int)$product->id_category_default);
        if (!empty($category->name)) {
            $orderItem->setCategoryName($category->name[$languageId]);
        }

        $weight = $sourceOrderItem['product_weight'] ?: (float)$product->weight ?: $defaultParcel->weight;
        $orderItem->setWeight(round($weight, 2));
        $orderItem->setWidth(ceil((float)$product->width ?: $defaultParcel->width));
        $orderItem->setLength(ceil((float)$product->depth ?: $defaultParcel->length));
        $orderItem->setHeight(ceil((float)$product->height ?: $defaultParcel->height));

        /** @var array $productCoverImage */
        $productCoverImage = \Image::getCover($product->id);
        if (!empty($productCoverImage)) {
            $link = new \Link();

            if (version_compare(_PS_VERSION_, '1.7.0.0', '<')) {
                $imageType = \ImageType::getFormatedName('home');
            } else {
                $imageType = \ImageType::getFormattedName('home');
            }

            /** @noinspection PhpDeprecationInspection */
            $productImageUrl = $link->getImageLink(
                $product->link_rewrite[$languageId],
                (int)$productCoverImage['id_image'],
                $imageType
            );
            $orderItem->setPictureUrl($productImageUrl);
        }

        // Customs item attributes, both driven by the merchant's data mapping on the customs settings
        // page: either the fields this module adds to the product Shipping tab, or any product
        // feature the merchant created. Values left empty here fall back to the configured customs
        // defaults in the core build.
        $tariffNumber = $this->resolveTariffNumber((int)$product->id);
        if ($tariffNumber !== '') {
            $orderItem->setTariffNumber($tariffNumber);
        }

        $countryOfOrigin = $this->resolveCountryOfOrigin((int)$product->id);
        if ($countryOfOrigin !== '') {
            $orderItem->setCountryOfOrigin($countryOfOrigin);
        }

        return $orderItem;
    }

    /**
     * Loads the stored customs data-source mapping for the current order build, or null when none is
     * configured. Reads the raw stored mapping (no Packlink proxy call).
     *
     * @return \Packlink\BusinessLogic\Customs\Models\CustomsMapping|null
     */
    private function loadCustomsMapping()
    {
        try {
            /** @var ConfigurationService $configService */
            $configService = ServiceRegister::getService(Configuration::CLASS_NAME);

            return $configService->getCustomsMappings();
        } catch (\Exception $e) {
            Logger::logWarning('Failed to load customs mapping: ' . $e->getMessage(), 'Integration');

            return null;
        }
    }

    /**
     * Backfills the customs mapping defaults from real, shipment-derived data so that international
     * customs invoices are not rejected by Packlink for blank required fields:
     *  - defaultCountry     (item country_of_origin fallback) <- default warehouse/sender country
     *  - defaultSenderTaxId (sender tax id)                    <- connected Packlink account tax id
     *
     * Only fills values that are currently empty (never overwrites a merchant-set value) and persists
     * the mapping so the core customs-invoice build (which reads these defaults) picks them up.
     *
     * @return void
     */
    private function ensureCustomsDefaults()
    {
        try {
            /** @var ConfigurationService $configService */
            $configService = ServiceRegister::getService(Configuration::CLASS_NAME);

            $mapping = $configService->getCustomsMappings();
            if ($mapping === null) {
                return;
            }

            $changed = false;

            if (empty($mapping->defaultCountry)) {
                $warehouse = $configService->getDefaultWarehouse();
                if ($warehouse !== null && !empty($warehouse->country)) {
                    $mapping->defaultCountry = $warehouse->country;
                    $changed = true;
                }
            }

            if (empty($mapping->defaultSenderTaxId)) {
                $user = $configService->getUserInfo();
                $taxId = ($user !== null && !empty($user->taxId)) ? $user->taxId : '';

                // The stored account info may predate the merchant adding their tax number in Packlink,
                // so when it is missing refresh it from Packlink. This lets a newly-set sender tax id
                // flow into customs invoices without requiring the merchant to reconnect the account.
                if ($taxId === '') {
                    try {
                        /** @var \Packlink\BusinessLogic\Http\Proxy $proxy */
                        $proxy = ServiceRegister::getService(\Packlink\BusinessLogic\Http\Proxy::CLASS_NAME);
                        $fresh = $proxy->getUserData();
                        if ($fresh !== null && !empty($fresh->taxId)) {
                            $taxId = $fresh->taxId;
                            $configService->setUserInfo($fresh);
                        }
                    } catch (\Exception $e) {
                        Logger::logWarning('Failed to refresh account tax id: ' . $e->getMessage(), 'Integration');
                    }
                }

                if ($taxId !== '') {
                    $mapping->defaultSenderTaxId = $taxId;
                    $changed = true;
                }
            }

            if ($changed) {
                $configService->setCustomsMappings($mapping);
            }
        } catch (\Exception $e) {
            Logger::logWarning('Failed to backfill customs defaults: ' . $e->getMessage(), 'Integration');
        }
    }

    /**
     * Resolves the receiver tax id from the source the merchant selected in the customs mapping
     * (mapping_receiver_tax_id); defaults to the module customer Tax ID field.
     *
     * @param PrestaShopOrder $sourceOrder
     *
     * @return string
     */
    private function resolveReceiverTaxId(PrestaShopOrder $sourceOrder)
    {
        return $this->customsDataProvider()->resolveReceiverTaxId(
            (int)$sourceOrder->id_customer,
            $this->getDeliveryVatNumber($sourceOrder)
        );
    }

    /**
     * Marks the draft as duties-paid when the shopper bought a DDP option, using the amount recorded at
     * order validation. Orders without a recorded selection are left untouched, so a non-DDP draft
     * carries no DDP keys at all.
     *
     * @param Order $order Core order being built.
     * @param PrestaShopOrder $sourceOrder Placed shop order.
     */
    private function applyDdpSelection(Order $order, PrestaShopOrder $sourceOrder)
    {
        try {
            // Freight for the customs invoice's shipment cost (C8): the transport alone, TAX-EXCLUDED,
            // minus the duty inside it when a duties-paid option was bought. Never the order total —
            // customs value is goods + freight, and the goods are already itemised on the invoice.
            //
            // Tax-excluded because that is the unit the checkout quote declared: CheckoutDdpService
            // sends the module's own hook cost, which PrestaShop takes tax-excluded and taxes itself.
            // Packlink prices the duty from goods + freight, so declaring a tax-INCLUDED freight here
            // while the quote declared a tax-excluded one has Packlink price the real shipment on a
            // higher customs value than it quoted, and bill a duty the shopper was never charged. The
            // shortfall is the merchant's. Both sides must be the same unit; this is that unit.
            $freight = (float)$sourceOrder->total_shipping_tax_excl;

            $repository = RepositoryRegistry::getRepository(CartDdpSelection::getClassName());

            $filter = new QueryFilter();
            $filter->where('orderId', Operators::EQUALS, (string)$sourceOrder->id);

            /** @var CartDdpSelection|null $selection */
            $selection = $repository->selectOne($filter);

            if ($selection !== null) {
                $order->setDdpSelected(true);
                $order->setDdpCost((float)$selection->getAmount());

                $porterage = $selection->getPorterage();

                if ($porterage !== null && (float)$porterage > 0.0) {
                    // Packlink's OWN carrier price for the chosen service, recorded when the shopper
                    // bought the option. Preferred over any derivation because it is the exact figure
                    // the checkout quote was made against, so the draft now declares the same freight
                    // the shopper was priced on.
                    //
                    // The subtraction below cannot reach it: what the shopper paid for shipping is
                    // porterage PLUS Packlink's platform fee, and the fee is not carrier freight.
                    // Measured on order #84 - paid 71.39, duty 26.40, so the derivation gave 44.99
                    // against a real carrier price of 44.00. Packlink bills on porterage either way, so
                    // the money was right, but the draft screen showed 26.49 where the shopper was
                    // charged 26.40, and the customs invoice declared a transport cost 0.99 too high.
                    $freight = (float)$porterage;
                } else {
                    // No recorded carrier price: a selection from before this was carried, or a quote
                    // that never produced one. Derive it as before - wrong by the platform fee, which is
                    // far better than declaring the whole shipping line or nothing at all.
                    //
                    // No tax factor. The duty rides inside the carrier price, so it is already part of
                    // total_shipping_tax_excl and comes out in that same tax-excluded unit.
                    //
                    // Scaling it by the shipping tax factor was what produced the earlier mismatch:
                    // subtracting amount x (1+t) from total_shipping_tax_incl reduces algebraically to
                    // T x (1+t), so the draft declared a tax-included transport no matter how right the
                    // subtraction looked.
                    $freight = max(0.0, $freight - (float)$selection->getAmount());
                }
            }

            if (method_exists($order, 'setShippingCost')) {
                $order->setShippingCost($freight);
            }
        } catch (\Exception $e) {
            Logger::logWarning(
                'Failed to read the DDP selection for order ' . (int)$sourceOrder->id . ': ' . $e->getMessage(),
                'Integration'
            );
        }
    }

    /**
     * Returns the customs data provider for the current build, creating it on demand for callers that
     * run outside getOrderAndShippingData().
     *
     * @return CustomsDataProvider
     */
    private function customsDataProvider()
    {
        if ($this->customsDataProvider === null) {
            $this->customsDataProvider = new CustomsDataProvider($this->customsMapping);
        }

        return $this->customsDataProvider;
    }

    /**
     * Resolves the company VAT from the source selected in the customs mapping (mapping_company_vat);
     * the native address VAT number is the only supported source today and is the default.
     *
     * @param PrestaShopOrder $sourceOrder
     *
     * @return string
     */
    private function resolveCompanyVat(PrestaShopOrder $sourceOrder)
    {
        return $this->getDeliveryVatNumber($sourceOrder);
    }

    /**
     * Resolves the item tariff number from whichever source the merchant mapped.
     *
     * A malformed value is dropped rather than sent: Packlink rejects a customs invoice whose tariff
     * number is not 6 to 8 digits, and dropping it lets the configured default apply instead of
     * failing the whole draft. Digits are extracted first, so a feature holding "6109 10 00" or
     * "HS 61091000" still maps cleanly.
     *
     * @param int $productId
     *
     * @return string Empty string when nothing usable is configured for this product.
     */
    private function resolveTariffNumber($productId)
    {
        return $this->customsDataProvider()->resolveTariffNumber($productId);
    }

    /**
     * Resolves the item country of origin from whichever source the merchant mapped, as an ISO
     * 3166-1 alpha-2 code.
     *
     * A merchant-created feature usually holds a country name ("Germany"), not a code, so the value
     * is resolved through the shop's country table as well as being accepted as a code.
     *
     * @param int $productId
     *
     * @return string Empty string when nothing usable is configured for this product.
     */
    private function resolveCountryOfOrigin($productId)
    {
        return $this->customsDataProvider()->resolveCountryOfOrigin($productId);
    }

    /**
     * Gets the order.
     *
     * @param int $orderId Shop order ID.
     *
     * @return PrestaShopOrder
     * @throws \Packlink\BusinessLogic\Order\Exceptions\OrderNotFound
     */
    private function getOrder($orderId)
    {
        $order = null;
        try {
            $order = new PrestaShopOrder($orderId);
        } catch (\PrestaShopDatabaseException $e) {
        } catch (\PrestaShopException $e) {
        }

        if (!\Validate::isLoadedObject($order)) {
            throw new OrderNotFound(TranslationUtility::__("Order with ID $orderId doesn't exist in the shop"));
        }

        return $order;
    }

    /**
     * Loads the module-owned customs data for every given product id in a single query and caches it
     * for the current order build, replacing a per-line N+1 lookup.
     *
     * @param int[] $productIds
     */
    private function preloadProductCustomsData(array $productIds)
    {
        $this->customsDataProvider()->preloadProducts($productIds);
    }

    /**
     * Returns the VAT number from the order delivery address (native PrestaShop field).
     *
     * @param PrestaShopOrder $sourceOrder
     *
     * @return string
     */
    private function getDeliveryVatNumber(PrestaShopOrder $sourceOrder)
    {
        try {
            $deliveryAddress = $this->loadDeliveryAddress((int)$sourceOrder->id_address_delivery);

            return !empty($deliveryAddress->vat_number) ? $deliveryAddress->vat_number : '';
        } catch (\Exception $e) {
            Logger::logWarning(
                'Failed to read delivery VAT number for order ' . (int)$sourceOrder->id . ': ' . $e->getMessage(),
                'Integration'
            );

            return '';
        }
    }

    /**
     * Loads (and caches for the current request) a PrestaShop delivery address by id, so the same
     * address is hydrated once across the order build instead of per accessor.
     *
     * @param int $addressId
     *
     * @return PrestaShopAddress
     */
    private function loadDeliveryAddress($addressId)
    {
        $addressId = (int)$addressId;

        if (!isset($this->deliveryAddressCache[$addressId])) {
            $this->deliveryAddressCache[$addressId] = new PrestaShopAddress($addressId);
        }

        return $this->deliveryAddressCache[$addressId];
    }
}
