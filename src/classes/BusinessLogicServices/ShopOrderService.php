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
use Packlink\PrestaShop\Classes\Entities\CartCarrierDropOffMapping;
use Packlink\PrestaShop\Classes\Entities\ProductCustomsData;
use Packlink\PrestaShop\Classes\Repositories\OrderRepository;
use Packlink\PrestaShop\Classes\Utility\CustomsDataProvider;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

/**
 * Class ShopOrderService
 *
 * @package Packlink\PrestaShop\Classes\BusinessLogicServices
 */
class ShopOrderService implements \Packlink\BusinessLogic\Order\Interfaces\ShopOrderService
{
    /**
     * Per-order cache of module-owned product customs data, keyed by product id. Preloaded once per
     * order build in a single query to avoid an N+1 lookup per order line. Null until preloaded.
     *
     * @var ProductCustomsData[]|null
     */
    private $productCustomsCache;
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

            // Customs receiver data. Honor the merchant's data-mapping selections (which PrestaShop
            // source feeds each customs field); the core invoice build falls back to the configured
            // mapping defaults when a value is absent.
            $this->customsMapping = $this->loadCustomsMapping();

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

        // Customs item attributes. The tariff-number source is driven by the customs mapping
        // (mapping_tariff_number); empty values fall back to the mapping defaults in the core build.
        $productCustoms = $this->getProductCustomsData((int)$product->id);
        if ($productCustoms !== null) {
            if (!empty($productCustoms->hsCode)
                && $this->tariffNumberSource() === CustomsMappingService::SOURCE_PRODUCT_HS_CODE
            ) {
                $orderItem->setTariffNumber($productCustoms->hsCode);
            }
            if (!empty($productCustoms->countryOfOrigin)) {
                $orderItem->setCountryOfOrigin($productCustoms->countryOfOrigin);
            }
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
     * Resolves the receiver tax id from the source the merchant selected in the customs mapping
     * (mapping_receiver_tax_id); defaults to the module customer Tax ID field.
     *
     * @param PrestaShopOrder $sourceOrder
     *
     * @return string
     */
    private function resolveReceiverTaxId(PrestaShopOrder $sourceOrder)
    {
        $source = ($this->customsMapping !== null && !empty($this->customsMapping->mappingReceiverTaxId))
            ? $this->customsMapping->mappingReceiverTaxId
            : CustomsMappingService::SOURCE_CUSTOMER_TAX_ID;

        if ($source === CustomsMappingService::SOURCE_ADDRESS_VAT) {
            return $this->getDeliveryVatNumber($sourceOrder);
        }

        return $this->getCustomerTaxId((int)$sourceOrder->id_customer);
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
     * Returns the tariff-number source selected in the customs mapping (mapping_tariff_number);
     * defaults to the product HS code field.
     *
     * @return string
     */
    private function tariffNumberSource()
    {
        return ($this->customsMapping !== null && !empty($this->customsMapping->mappingTariffNumber))
            ? $this->customsMapping->mappingTariffNumber
            : CustomsMappingService::SOURCE_PRODUCT_HS_CODE;
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
     * Returns the module-owned customs data for a product, or null when none is stored.
     *
     * @param int $productId
     *
     * @return ProductCustomsData|null
     */
    private function getProductCustomsData($productId)
    {
        $productId = (int)$productId;

        if (is_array($this->productCustomsCache)) {
            return isset($this->productCustomsCache[$productId]) ? $this->productCustomsCache[$productId] : null;
        }

        // Fallback single lookup for callers outside the preloaded order-build path.
        return CustomsDataProvider::getProductCustomsData($productId);
    }

    /**
     * Loads the module-owned customs data for every given product id in a single query and caches it
     * for the current order build, replacing a per-line N+1 lookup.
     *
     * @param int[] $productIds
     */
    private function preloadProductCustomsData(array $productIds)
    {
        $this->productCustomsCache = array();

        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if (empty($productIds)) {
            return;
        }

        try {
            $repository = RepositoryRegistry::getRepository(ProductCustomsData::CLASS_NAME);

            $query = new QueryFilter();
            $query->where('productId', 'IN', $productIds);

            /** @var ProductCustomsData[] $rows */
            $rows = $repository->select($query);
            foreach ($rows as $row) {
                $this->productCustomsCache[(int)$row->productId] = $row;
            }
        } catch (\Exception $e) {
            Logger::logWarning('Failed to preload product customs data: ' . $e->getMessage(), 'Integration');
        }
    }

    /**
     * Returns the private-person tax id stored for a customer, or an empty string.
     *
     * @param int $customerId
     *
     * @return string
     */
    private function getCustomerTaxId($customerId)
    {
        return CustomsDataProvider::getCustomerTaxId($customerId);
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
