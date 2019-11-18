<?php
/** @noinspection PhpDocRedundantThrowsInspection */

namespace Packlink\PrestaShop\Classes\Repositories;

use Address as PrestaShopAddress;
use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ORM\QueryFilter\Operators;
use Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Logeecom\Infrastructure\ServiceRegister;
use Order as PrestaShopOrder;
use Packlink\BusinessLogic\Configuration;
use Packlink\BusinessLogic\Http\DTO\Shipment;
use Packlink\BusinessLogic\Http\DTO\ShipmentLabel;
use Packlink\BusinessLogic\Http\DTO\Tracking;
use Packlink\BusinessLogic\Order\Exceptions\OrderNotFound;
use Packlink\BusinessLogic\Order\Models\OrderShipmentDetails;
use Packlink\BusinessLogic\Order\Objects\Address;
use Packlink\BusinessLogic\Order\Objects\Item;
use Packlink\BusinessLogic\Order\Objects\Order;
use Packlink\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Packlink\BusinessLogic\ShippingMethod\Utility\ShipmentStatus;
use Packlink\PrestaShop\Classes\BusinessLogicServices\CarrierService;
use Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService;
use Packlink\PrestaShop\Classes\Entities\CartCarrierDropOffMapping;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

/**
 * Class OrderRepository
 *
 * @package Packlink\PrestaShop\Classes\Repositories
 */
class OrderRepository implements \Packlink\BusinessLogic\Order\Interfaces\OrderRepository
{
    const PACKLINK_ORDER_DRAFT_FIELD = 'packlink_order_draft';
    /**
     * Shop order details repository.
     *
     * @var BaseRepository
     */
    private $orderDetailsRepository;

    /**
     * Returns shipment references of the orders that have not yet been completed.
     *
     * @return array Array of shipment references.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function getIncompleteOrderReferences()
    {
        $filter = new QueryFilter();
        $orderReferences = array();

        $filter->where('status', Operators::NOT_EQUALS, ShipmentStatus::STATUS_DELIVERED);
        /** @var \Packlink\BusinessLogic\Order\Models\OrderShipmentDetails $orderDetails */
        /** @noinspection OneTimeUseVariablesInspection */
        $orders = $this->getOrderDetailsRepository()->select($filter);

        foreach ($orders as $orderDetails) {
            if ($orderDetails->getReference() !== null) {
                $orderReferences[] = $orderDetails->getReference();
            }
        }

        return $orderReferences;
    }

    /**
     * Retrieves list of order references where order is in one of the provided statuses.
     *
     * @param array $statuses List of order statuses.
     *
     * @return string[] Array of shipment references.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function getOrderReferencesWithStatus(array $statuses)
    {
        $filter = new QueryFilter();
        $filter->where('status', Operators::IN, $statuses);
        $orders = $this->getOrderDetailsRepository()->select($filter);

        $result = array();
        /** @var OrderShipmentDetails $order */
        foreach ($orders as $order) {
            $result[] =$order->getReference();
        }

        return $result;
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
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    public function getOrderAndShippingData($orderId)
    {
        $sourceOrder = new PrestaShopOrder($orderId);
        $order = new Order();

        if ($sourceOrder === null) {
            Logger::logWarning(TranslationUtility::__('Source order not found'), 'Integration');

            return $order;
        }

        $currencyId = (int)$sourceOrder->id_currency;
        $currency = \Currency::getCurrency($currencyId);

        $order->setId($orderId);
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

        return $order;
    }

    /**
     * Sets order packlink reference number.
     *
     * @param string $orderId Unique order id.
     * @param string $shipmentReference Packlink shipment reference.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Packlink\BusinessLogic\Order\Exceptions\OrderNotFound When order with provided id is not found.
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    public function setReference($orderId, $shipmentReference)
    {
        $this->checkIfOrderExists($orderId);

        $orderDetails = $this->getOrderDetailsById($orderId);

        if ($orderDetails === null) {
            $orderDetails = new OrderShipmentDetails();
            $orderDetails->setOrderId($orderId);
            $this->getOrderDetailsRepository()->save($orderDetails);
        }

        $orderDetails->setReference($shipmentReference);

        $this->getOrderDetailsRepository()->update($orderDetails);
        $this->setOrderDraftReference($orderId, $shipmentReference);
    }

    /**
     * Sets label identified by order ID and link to PDF to have been printed.
     *
     * @param int $orderId ID of the order that the shipment label belongs to.
     * @param string $link Link to PDF.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function setLabelPrinted($orderId, $link)
    {
        $orderDetails = $this->getOrderDetailsById($orderId);
        if ($orderDetails === null) {
            Logger::logWarning(TranslationUtility::__('Order details not found'), 'Integration');

            return;
        }

        $labels = $orderDetails->getShipmentLabels();
        foreach ($labels as $label) {
            if ($label->getLink() === $link) {
                $label->setPrinted(true);
            }
        }

        $this->getOrderDetailsRepository()->update($orderDetails);
    }

    /**
     * Returns shipment labels for order identified by provided ID.
     *
     * @param int $orderId ID of the order
     *
     * @return ShipmentLabel[]
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function getLabelsByOrderId($orderId)
    {
        $orderDetails = $this->getOrderDetailsById($orderId);

        return $orderDetails !== null ? $orderDetails->getShipmentLabels() : array();
    }

    /**
     * Returns order details entity with provided order ID.
     *
     * @param int $orderId ID of the order.
     *
     * @return OrderShipmentDetails | null Order packlink shipment details entity or null if not found.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function getOrderDetailsById($orderId)
    {
        $filter = new QueryFilter();

        $filter->where('orderId', Operators::EQUALS, $orderId);
        /** @var OrderShipmentDetails $orderDetails */
        /** @noinspection OneTimeUseVariablesInspection */
        $orderDetails = $this->getOrderDetailsRepository()->selectOne($filter);

        return $orderDetails;
    }

    /**
     * Saves order details entity using order details repository.
     *
     * @param OrderShipmentDetails $orderDetails Shop order details entity.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     */
    public function saveOrderDetails(OrderShipmentDetails $orderDetails)
    {
        if ($orderDetails->getId() === null) {
            $this->getOrderDetailsRepository()->save($orderDetails);
        } else {
            $this->getOrderDetailsRepository()->update($orderDetails);
        }
    }

    /**
     * Sets order packlink shipment tracking history to an order by shipment reference.
     *
     * @param Shipment $shipment
     * @param Tracking[] $trackingHistory Shipment tracking history.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Packlink\BusinessLogic\Order\Exceptions\OrderNotFound When order with provided reference is not found.
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    public function updateTrackingInfo(Shipment $shipment, array $trackingHistory)
    {
        $orderDetails = $this->getOrderDetailsByReference($shipment->reference);

        if (!empty($trackingHistory)) {
            $trackingHistory = $this->sortTrackingRecords($trackingHistory);
            $latestTrackingRecord = $trackingHistory[0];
            $orderDetails->setShippingStatus($latestTrackingRecord->description, $latestTrackingRecord->timestamp);
        }

        if ($shipment !== null) {
            $orderDetails->setShippingCost($shipment->price);
            $orderDetails->setCarrierTrackingUrl($shipment->carrierTrackingUrl);
            if (!empty($shipment->trackingCodes)) {
                $order = new PrestaShopOrder($orderDetails->getOrderId());
                $order->setWsShippingNumber($shipment->trackingCodes[0]);

                $orderDetails->setCarrierTrackingNumbers($shipment->trackingCodes);
            }
        }

        $this->getOrderDetailsRepository()->update($orderDetails);
    }

    /**
     * Sets order packlink shipping status to an order by shipment reference.
     *
     * @param string $shipmentReference Packlink shipment reference.
     * @param string $shippingStatus Packlink shipping status.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Packlink\BusinessLogic\Order\Exceptions\OrderNotFound When order with provided reference is not found.
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    public function setShippingStatusByReference($shipmentReference, $shippingStatus)
    {
        $orderDetails = $this->getOrderDetailsByReference($shipmentReference);

        $this->setSourceOrderStatus($orderDetails->getOrderId(), $shippingStatus);

        $orderDetails->setShippingStatus($shippingStatus);
        $this->getOrderDetailsRepository()->update($orderDetails);
    }

    /**
     * Sets shipping price to an order by shipment reference.
     *
     * @param string $shipmentReference Packlink shipment reference.
     * @param float $price Shipment price.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Packlink\BusinessLogic\Order\Exceptions\OrderNotFound When order with provided reference is not found.
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    public function setShippingPriceByReference($shipmentReference, $price)
    {
        $orderDetails = $this->getOrderDetailsByReference($shipmentReference);

        $orderDetails->setShippingCost($price);
        $this->getOrderDetailsRepository()->update($orderDetails);
    }

    /**
     * Marks shipment identified by provided reference as deleted on Packlink.
     *
     * @param string $shipmentReference Packlink shipment reference.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Packlink\BusinessLogic\Order\Exceptions\OrderNotFound
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function markShipmentDeleted($shipmentReference)
    {
        $orderDetails = $this->getOrderDetailsByReference($shipmentReference);

        $orderDetails->setDeleted(true);

        $this->getOrderDetailsRepository()->update($orderDetails);
    }

    /**
     * Returns whether shipment identified by provided reference is deleted on Packlink or not.
     *
     * @param string $shipmentReference Packlink shipment reference.
     *
     * @return bool Returns TRUE if shipment has been deleted; otherwise returns FALSE.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Packlink\BusinessLogic\Order\Exceptions\OrderNotFound
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function isShipmentDeleted($shipmentReference)
    {
        $orderDetails = $this->getOrderDetailsByReference($shipmentReference);

        return $orderDetails->isDeleted();
    }

    /**
     * Returns order details entity with provided shipment reference, or throws an exception if it doesn't exist.
     *
     * @param string $reference Packlink order shipment reference.
     *
     * @return OrderShipmentDetails Order details.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Packlink\BusinessLogic\Order\Exceptions\OrderNotFound
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function getOrderDetailsByReference($reference)
    {
        $filter = new QueryFilter();

        $filter->where('reference', Operators::EQUALS, $reference);
        /** @var OrderShipmentDetails $orderDetails */
        $orderDetails = $this->getOrderDetailsRepository()->selectOne($filter);

        if ($orderDetails === null) {
            throw new OrderNotFound(
                TranslationUtility::__(
                    "Order with shipment reference $reference doesn't exist in the shop"
                )
            );
        }

        return $orderDetails;
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
            return null;
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
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    private function getAddress(PrestaShopOrder $shopOrder)
    {
        $deliveryAddressId = (int)$shopOrder->id_address_delivery;

        $shippingAddress = new Address();
        $deliveryAddress = new PrestaShopAddress($deliveryAddressId);
        $customer = new \Customer($shopOrder->id_customer);
        /** @noinspection PhpMethodParametersCountMismatchInspection */
        $country = new \Country($deliveryAddress->id_country);

        if ($country !== null) {
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

        if ($customer !== null) {
            $shippingAddress->setEmail($customer->email);
            $shippingAddress->setName($deliveryAddress->firstname ?: $customer->firstname);
            $shippingAddress->setSurname($deliveryAddress->lastname ?: $customer->lastname);
        }

        return $shippingAddress;
    }

    /**
     * Sets shipping status on source order on PrestaShop.
     *
     * @param int $orderId ID of the order.
     * @param string $shippingStatus Shipping status from Packlink.
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    private function setSourceOrderStatus($orderId, $shippingStatus)
    {
        $order = new PrestaShopOrder($orderId);
        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        $statusMappings = $configService->getOrderStatusMappings();

        if (!array_key_exists($shippingStatus, $statusMappings)) {
            Logger::logWarning(
                TranslationUtility::__('Order status mapping not found.'),
                'Integration'
            );

            return;
        }

        if ((int)$order->getCurrentState() !== (int)$statusMappings[$shippingStatus]) {
            $order->setCurrentState((int)$statusMappings[$shippingStatus]);
            $order->save();
        }
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

        if ($carrier === null) {
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
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
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
            /** @var \ProductCore $product */
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
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
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

        $orderItem->setWeight(round((float)$product->weight ?: $defaultParcel->weight, 2));
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
     * Checks if order with provided ID exists in the shop and throws an exception if it doesn't.
     *
     * @param int $orderId Shop order ID.
     *
     * @throws \Packlink\BusinessLogic\Order\Exceptions\OrderNotFound
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    private function checkIfOrderExists($orderId)
    {
        $order = new PrestaShopOrder($orderId);

        if ($order === null) {
            throw new OrderNotFound(
                TranslationUtility::__("Order with ID $orderId doesn't exist in the shop")
            );
        }
    }

    /**
     * Saves link to order draft on Packlink to PrestaShop orders table.
     *
     * @param int $orderId ID of the order.
     * @param string $reference Shipment reference.
     */
    private function setOrderDraftReference($orderId, $reference)
    {
        \Db::getInstance()->update(
            'orders',
            array(self::PACKLINK_ORDER_DRAFT_FIELD => pSQL($reference)),
            "id_order = $orderId"
        );
    }

    /**
     * Sort tracking history records by timestamps in descending order.
     *
     * @param Tracking[] $trackingRecords Array of tracking history records.
     *
     * @return array Sorted array of tracking history records.
     */
    private function sortTrackingRecords(array $trackingRecords)
    {
        usort(
            $trackingRecords,
            function ($first, $second) {
                if ($first->timestamp === $second->timestamp) {
                    return 0;
                }

                return ($first->timestamp < $second->timestamp) ? 1 : -1;
            }
        );

        return $trackingRecords;
    }

    /**
     * Returns shop order details repository.
     *
     * @return BaseRepository
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    private function getOrderDetailsRepository()
    {
        if ($this->orderDetailsRepository === null) {
            $this->orderDetailsRepository = RepositoryRegistry::getRepository(OrderShipmentDetails::getClassName());
        }

        return $this->orderDetailsRepository;
    }
}
