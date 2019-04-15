<?php
/**
 * 2019 Packlink
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    Packlink <support@packlink.com>
 * @copyright 2019 Packlink Shipping S.L
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace Packlink\PrestaShop\Classes\Repositories;

use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ORM\QueryFilter\Operators;
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
use Packlink\BusinessLogic\ShippingMethod\Utility\ShipmentStatus;
use Packlink\PrestaShop\Classes\BusinessLogicServices\CarrierService;
use Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService;
use Packlink\PrestaShop\Classes\Entities\CartCarrierDropOffMapping;
use Packlink\PrestaShop\Classes\Entities\ShopOrderDetails;
use Packlink\PrestaShop\Classes\Objects\ShipmentLabel;
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
     */
    public function getIncompleteOrderReferences()
    {
        $filter = new QueryFilter();
        $orderReferences = array();

        $filter->where('status', Operators::NOT_EQUALS, ShipmentStatus::STATUS_DELIVERED);
        /** @var ShopOrderDetails $orderDetails */
        /** @noinspection OneTimeUseVariablesInspection */
        $orders = $this->getOrderDetailsRepository()->select($filter);

        foreach ($orders as $orderDetails) {
            if ($orderDetails->getShipmentReference() !== null && !$orderDetails->isDeleted()) {
                $orderReferences[] = $orderDetails->getShipmentReference();
            }
        }

        return $orderReferences;
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
     */
    public function setReference($orderId, $shipmentReference)
    {
        $this->checkIfOrderExists($orderId);

        $orderDetails = $this->getOrderDetailsById($orderId);

        if ($orderDetails === null) {
            $orderDetails = new ShopOrderDetails();
            $orderDetails->setOrderId($orderId);
            $this->getOrderDetailsRepository()->save($orderDetails);
        }

        $orderDetails->setShipmentReference($shipmentReference);

        $this->getOrderDetailsRepository()->update($orderDetails);
        $this->setOrderDraftLink($orderId, $shipmentReference);
    }

    /**
     * Sets order packlink shipment labels to an order by shipment reference.
     *
     * @param string $shipmentReference Packlink shipment reference.
     * @param string[] $labels Packlink shipment labels.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Packlink\BusinessLogic\Order\Exceptions\OrderNotFound When order with provided reference is not found.
     * @throws \PrestaShopDatabaseException
     */
    public function setLabelsByReference($shipmentReference, array $labels)
    {
        $orderDetails = $this->getOrderDetailsByReference($shipmentReference);

        $orderDetails->setShipmentLabels($labels);

        $this->getOrderDetailsRepository()->update($orderDetails);
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
     * @return ShopOrderDetails | null Shop order details entity or null if not found.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     */
    public function getOrderDetailsById($orderId)
    {
        $filter = new QueryFilter();

        $filter->where('orderId', Operators::EQUALS, $orderId);
        /** @var ShopOrderDetails $orderDetails */
        /** @noinspection OneTimeUseVariablesInspection */
        $orderDetails = $this->getOrderDetailsRepository()->selectOne($filter);

        return $orderDetails;
    }

    /**
     * Saves order details entity using order details repository.
     *
     * @param ShopOrderDetails $orderDetails Shop order details entity.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     */
    public function saveOrderDetails(ShopOrderDetails $orderDetails)
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
     * @param string $shipmentReference Packlink shipment reference.
     * @param Tracking[] $trackingHistory Shipment tracking history.
     * @param Shipment $shipmentDetails
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Packlink\BusinessLogic\Order\Exceptions\OrderNotFound When order with provided reference is not found.
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function updateTrackingInfo($shipmentReference, array $trackingHistory, Shipment $shipmentDetails)
    {
        $orderDetails = $this->getOrderDetailsByReference($shipmentReference);

        if (!empty($trackingHistory)) {
            $trackingHistory = $this->sortTrackingRecords($trackingHistory);
            $latestTrackingRecord = $trackingHistory[0];
            $orderDetails->setShippingStatus($latestTrackingRecord->description, $latestTrackingRecord->timestamp);
        }

        if ($shipmentDetails !== null) {
            $orderDetails->setPacklinkShippingPrice($shipmentDetails->price);
            $orderDetails->setCarrierTrackingUrl($shipmentDetails->carrierTrackingUrl);
            if (!empty($shipmentDetails->trackingCodes)) {
                $order = new PrestaShopOrder($orderDetails->getOrderId());
                $order->setWsShippingNumber($shipmentDetails->trackingCodes[0]);

                $orderDetails->setCarrierTrackingNumbers($shipmentDetails->trackingCodes);
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
     */
    public function setShippingPriceByReference($shipmentReference, $price)
    {
        $orderDetails = $this->getOrderDetailsByReference($shipmentReference);

        $order = new PrestaShopOrder($orderDetails->getOrderId());
        $order->updateShippingCost($price);

        $orderDetails->setPacklinkShippingPrice($price);
        $this->getOrderDetailsRepository()->update($orderDetails);
    }

    /**
     * Marks order as deleted on the system.
     *
     * @param string $shipmentReference Packlink shipment reference.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Packlink\BusinessLogic\Order\Exceptions\OrderNotFound
     */
    public function setDeleted($shipmentReference)
    {
        $orderDetails = $this->getOrderDetailsByReference($shipmentReference);

        $orderDetails->setDeleted(true);

        $this->getOrderDetailsRepository()->update($orderDetails);
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
     */
    private function getAddress(PrestaShopOrder $shopOrder)
    {
        $deliveryAddressId = (int)$shopOrder->id_address_delivery;

        $shippingAddress = new Address();
        $deliveryAddress = new \Address($deliveryAddressId);
        $customer = new \Customer($shopOrder->id_customer);
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

        if ($customer !== null) {
            $shippingAddress->setEmail($customer->email);
            $shippingAddress->setName($customer->firstname);
            $shippingAddress->setSurname($customer->lastname);
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
     * Returns order details entity with provided shipment reference, or throws an exception if it doesn't exist.
     *
     * @param string $shipmentReference Packlink order shipment reference.
     *
     * @return ShopOrderDetails Order details.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Packlink\BusinessLogic\Order\Exceptions\OrderNotFound
     * @throws \PrestaShopDatabaseException
     */
    private function getOrderDetailsByReference($shipmentReference)
    {
        $filter = new QueryFilter();

        $filter->where('shipmentReference', Operators::EQUALS, $shipmentReference);
        /** @var ShopOrderDetails $orderDetails */
        $orderDetails = $this->getOrderDetailsRepository()->selectOne($filter);

        if ($orderDetails === null) {
            throw new OrderNotFound(
                TranslationUtility::__(
                    "Order with shipment reference $shipmentReference doesn't exist in the shop"
                )
            );
        }

        return $orderDetails;
    }

    /**
     * Sets order shipping details.
     *
     * @param Order $order Packlink order object.
     * @param int $carrierId ID of PrestaShop carrier.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
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
            $product = new \Product((int) $sourceOrderItem['product_id']);
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

        $orderItem->setWeight(round((float)$product->weight ?: $defaultParcel->weight, 2));
        $orderItem->setWidth(ceil((float)$product->width ?: $defaultParcel->width));
        $orderItem->setLength(ceil((float)$product->depth ?: $defaultParcel->length));
        $orderItem->setHeight(ceil((float)$product->height ?: $defaultParcel->height));

        /** @var array $productCoverImage */
        $productCoverImage = \Image::getCover($product->id);
        if (!empty($productCoverImage)) {
            $link = new \Link();
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
    private function setOrderDraftLink($orderId, $reference)
    {
        \Db::getInstance()->update(
            'orders',
            array(self::PACKLINK_ORDER_DRAFT_FIELD => pSQL($this->getOrderDraftUrl($reference))),
            "id_order = $orderId"
        );
    }

    /**
     * Returns link to order draft on Packlink for the provided shipment reference.
     *
     * @param string $reference Shipment reference.
     *
     * @return string Link to order draft on Packlink.
     */
    private function getOrderDraftUrl($reference)
    {
        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        $userCountry = $configService->getUserInfo() !== null
            ? \Tools::strtolower($configService->getUserInfo()->country)
            : 'es';

        return "https://pro.packlink.$userCountry/private/shipments/$reference";
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
            $this->orderDetailsRepository = RepositoryRegistry::getRepository(ShopOrderDetails::getClassName());
        }

        return $this->orderDetailsRepository;
    }
}
