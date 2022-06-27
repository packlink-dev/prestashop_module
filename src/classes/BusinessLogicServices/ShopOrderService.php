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
use Packlink\PrestaShop\Classes\Repositories\OrderRepository;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

/**
 * Class ShopOrderService
 *
 * @package Packlink\PrestaShop\Classes\BusinessLogicServices
 */
class ShopOrderService implements \Packlink\BusinessLogic\Order\Interfaces\ShopOrderService
{
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

            $order->setId($orderId);
            $order->setOrderNumber($orderId);
            $order->setCustomerId((int)$sourceOrder->id_customer);
            $order->setCurrency($currency['iso_code']);
            $order->setTotalPrice((float)$sourceOrder->total_paid_tax_incl);
            $order->setBasePrice((float)$sourceOrder->total_paid_tax_excl);
            $dropOffId = $this->getDropOffId($sourceOrder);
            if ($dropOffId) {
                $order->setShippingDropOffId($dropOffId);
            }

            $order->setShippingAddress($this->getAddress($sourceOrder));

            $this->setOrderShippingDetails($order, $sourceOrder->id_carrier);
            $order->setItems($this->getOrderItems($sourceOrder));
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
        $deliveryAddress = new PrestaShopAddress($deliveryAddressId);
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
            /** @noinspection PhpDeprecationInspection */
            $productImageUrl = $link->getImageLink(
                $product->link_rewrite[$languageId],
                (int)$productCoverImage['id_image'],
                \ImageType::getFormatedName('home')
            );
            $orderItem->setPictureUrl($productImageUrl);
        }

        return $orderItem;
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
}
