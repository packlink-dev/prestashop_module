<?php

namespace Packlink\PrestaShop\Classes\Repositories;

use Db;
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
            $updateSuccess = $this->updateOrderStateInDb($orderId, $stateId, $order->getCurrentState(), $order->date_upd);

            if ($updateSuccess) {
                $order->setCurrentState($stateId);
                $order->save();
            }
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
        $order->setWsShippingNumber($trackingNumber);
    }

    /**
     * Gets the order.
     *
     * @param int $orderId
     *
     * @return \Order An order instance.
     *
     * @throws \Packlink\BusinessLogic\Order\Exceptions\OrderNotFound If order does not exist.
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

    /**
     * Updates the order state in the database if the current state and update time match.
     *
     * @param int $orderId The order ID.
     * @param int $stateId The new state ID.
     * @param int $currentState The current state of the order.
     * @param string $currentDateUpd The current update time of the order.
     *
     * @return bool True if the update affected rows, false otherwise.
     */
    private function updateOrderStateInDb($orderId, $stateId, $currentState, $currentDateUpd)
    {
        $db = Db::getInstance();
        $newUpdateTime = date('Y-m-d H:i:s', time());

        $query = "
        UPDATE " . _DB_PREFIX_ . "orders
        SET current_state = $stateId, date_upd = '$newUpdateTime'
        WHERE id_order = $orderId
          AND current_state = $currentState
          AND date_upd = '$currentDateUpd'
    ";

        $db->execute($query);

        return $db->Affected_Rows() > 0;
    }
}
