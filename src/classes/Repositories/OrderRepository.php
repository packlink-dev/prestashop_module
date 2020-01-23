<?php

namespace Packlink\PrestaShop\Classes\Repositories;

use Packlink\BusinessLogic\Order\Exceptions\OrderNotFound;

/**
 * Class OrderRepository.
 *
 * @package Packlink\PrestaShop\Classes\Repositories
 */
class OrderRepository
{
    const PACKLINK_ORDER_DRAFT_FIELD = 'packlink_order_draft';

    /**
     * Sets the Packlink reference to the order.
     *
     * @param int $orderId
     * @param string $reference
     *
     * @throws \Packlink\BusinessLogic\Order\Exceptions\OrderNotFound
     */
    public function updateShipmentReference($orderId, $reference)
    {
        $this->getOrder($orderId);

        \Db::getInstance()->update(
            'orders',
            array(self::PACKLINK_ORDER_DRAFT_FIELD => pSQL($reference)),
            "id_order = $orderId"
        );
    }

    /**
     * Updates order state.
     *
     * @param int $orderId
     * @param int $stateId
     *
     * @throws \Packlink\BusinessLogic\Order\Exceptions\OrderNotFound
     * @noinspection PhpDocMissingThrowsInspection
     * @noinspection PhpUnhandledExceptionInspection
     */
    public function updateOrderState($orderId, $stateId)
    {
        $order = $this->getOrder($orderId);

        if ((int)$order->getCurrentState() !== $stateId) {
            $order->setCurrentState($stateId);
            $order->save();
        }
    }

    /**
     * Sets the tracking number for order.
     *
     * @param int $orderId
     * @param string $trackingNumber
     *
     * @throws \Packlink\BusinessLogic\Order\Exceptions\OrderNotFound
     */
    public function setTrackingNumber($orderId, $trackingNumber)
    {
        $order = $this->getOrder($orderId);
        $order->setWsShippingNumber($trackingNumber[0]);
    }

    /**
     * Gets the order.
     *
     * @param int $orderId
     *
     * @return \Order An order instance.
     *
     * @throws \Packlink\BusinessLogic\Order\Exceptions\OrderNotFound If order does not exist.
     * @noinspection PhpUndefinedClassInspection
     */
    private function getOrder($orderId)
    {
        $order = null;
        try {
            $order = new \Order($orderId);
        } catch (\PrestaShopDatabaseException $e) {
        } catch (\PrestaShopException $e) {
        }

        if (!\Validate::isLoadedObject($order)) {
            throw new OrderNotFound('Order with ID ' . $orderId . ' not found.');
        }

        return $order;
    }
}
