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

namespace Packlink\PrestaShop\Classes\Tasks;

use Logeecom\Infrastructure\Http\Exceptions\HttpUnhandledException;
use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\TaskExecution\Task;
use Logeecom\Infrastructure\Utility\TimeProvider;
use Packlink\BusinessLogic\Http\DTO\Shipment;
use Packlink\BusinessLogic\Http\Proxy;
use Packlink\BusinessLogic\Order\Interfaces\OrderRepository;
use Packlink\BusinessLogic\ShippingMethod\Utility\ShipmentStatus;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

class UpgradeShopOrderDetailsTask extends Task
{
    const INITIAL_PROGRESS_PERCENT = 5;
    const DEFAULT_BATCH_SIZE = 100;

    /**
     * @var array
     */
    private $ordersToSync;
    /**
     * @var int
     */
    private $batchSize;
    /**
     * @var int
     */
    private $numberOfOrders;
    /**
     * @var int
     */
    private $currentProgress;
    /**
     * @var \Packlink\PrestaShop\Classes\Repositories\OrderRepository
     */
    private $orderRepository;

    /**
     * UpgradeShopOrderDetailsTask constructor.
     *
     * @param array $oldOrders
     */
    public function __construct(array $oldOrders)
    {
        $this->ordersToSync = $oldOrders;
        $this->batchSize = self::DEFAULT_BATCH_SIZE;
        $this->numberOfOrders = count($this->ordersToSync);
        $this->currentProgress = self::INITIAL_PROGRESS_PERCENT;
    }

    /**
     * Returns string representation of object.
     *
     * @inheritdoc
     */
    public function serialize()
    {
        return serialize(
            array(
                $this->ordersToSync,
                $this->batchSize,
                $this->numberOfOrders,
                $this->currentProgress,
            )
        );
    }

    /**
     * Constructs the object.
     *
     * @inheritdoc
     */
    public function unserialize($serialized)
    {
        list(
            $this->ordersToSync,
            $this->batchSize,
            $this->numberOfOrders,
            $this->currentProgress,
        ) = unserialize($serialized);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $this->reportProgress($this->currentProgress);

        if ($this->numberOfOrders === 0) {
            $this->reportProgress(100);

            return;
        }

        /** @var Proxy $proxy */
        $proxy = ServiceRegister::getService(Proxy::CLASS_NAME);
        /** @var TimeProvider $timeProvider */
        $timeProvider = ServiceRegister::getService(TimeProvider::CLASS_NAME);

        $count = count($this->ordersToSync);

        while ($count > 0) {
            $orders = $this->getBatchOrders();
            $this->reportAlive();

            foreach ($orders as $order) {
                if (!$this->setReference($order['id_order'], $order['draft_reference'])) {
                    continue;
                }

                $orderCreated = $timeProvider->deserializeDateString($order['date_add'], 'Y-m-d H:i:s');

                if ($orderCreated < $timeProvider->getDateTime(strtotime('-60 days'))) {
                    $this->setDeleted($order['draft_reference']);

                    continue;
                }

                try {
                    $shipment = $proxy->getShipment($order['draft_reference']);
                } catch (\Exception $e) {
                    $shipment = null;
                }

                if ($shipment !== null) {
                    $this->setLabels($order['draft_reference'], $shipment->status, $proxy);
                    $this->setShipmentStatus($order['draft_reference'], $shipment);
                    $this->setTrackingInfo($order['draft_reference'], $proxy, $shipment);
                    $this->setShipmentPrice($order['draft_reference'], $shipment->price);
                } else {
                    $this->setDeleted($order['draft_reference']);
                }
            }

            $this->removeFinishedBatch();
            $this->reportProgressForBatch();
            $count = count($this->ordersToSync);
        }

        $this->reportProgress(100);
    }

    /**
     * Reduces batch size.
     *
     * @throws \Logeecom\Infrastructure\Http\Exceptions\HttpUnhandledException
     */
    public function reconfigure()
    {
        if ($this->batchSize >= 100) {
            $this->batchSize -= 50;
        } elseif ($this->batchSize > 10 && $this->batchSize < 100) {
            $this->batchSize -= 10;
        } elseif ($this->batchSize > 1 && $this->batchSize <= 10) {
            -- $this->batchSize;
        } else {
            throw new HttpUnhandledException(TranslationUtility::__('Batch size can not be smaller than 1'));
        }
    }

    /**
     * Determines whether task can be reconfigured.
     *
     * @return bool TRUE if task can be reconfigured; otherwise, FALSE.
     */
    public function canBeReconfigured()
    {
        return true;
    }

    /**
     * Creates reference.
     *
     * @param string $orderId
     * @param string $referenceId
     *
     * @return bool
     */
    protected function setReference($orderId, $referenceId)
    {
        try {
            $this->getOrderRepository()->setReference($orderId, $referenceId);
        } catch (\Exception $e) {
            Logger::logError(
                TranslationUtility::__('Failed to create reference for order %d', array($orderId)),
                'Integration'
            );

            return false;
        }

        return true;
    }

    /**
     * Sets labels for order.
     *
     * @param string $reference Packlink shipment reference.
     * @param string $orderState State of the order.
     * @param Proxy $proxy Packlink proxy.
     */
    protected function setLabels($reference, $orderState, $proxy)
    {
        $validStates = array(
            'READY_TO_PRINT',
            'READY_FOR_COLLECTION',
            'IN_TRANSIT',
            'DELIVERED',
        );

        if (in_array($orderState, $validStates, true)) {
            try {
                $labels = $proxy->getLabels($reference);
                $this->getOrderRepository()->setLabelsByReference($reference, $labels);
            } catch (\Exception $e) {
                Logger::logError(
                    TranslationUtility::__('Failed to set labels for order with reference %s', array($reference)),
                    'Integration'
                );
            }
        }
    }

    /**
     * Sets tracking info for order.
     *
     * @param string $reference
     * @param Proxy $proxy
     * @param \Packlink\BusinessLogic\Http\DTO\Shipment $shipment
     */
    protected function setTrackingInfo($reference, $proxy, $shipment)
    {
        try {
            $trackingInfo = $proxy->getTrackingInfo($reference);
            $this->getOrderRepository()->updateTrackingInfo($reference, $trackingInfo, $shipment);
        } catch (\Exception $e) {
            Logger::logError(
                TranslationUtility::__(
                    'Failed to set tracking info for order with reference %s',
                    array($reference)
                ),
                'Integration'
            );
        }
    }

    /**
     * Sets order status.
     *
     * @param string $reference
     * @param Shipment $shipment
     */
    protected function setShipmentStatus($reference, $shipment)
    {
        try {
            $this->getOrderRepository()->setShippingStatusByReference(
                $reference,
                ShipmentStatus::getStatus($shipment->status)
            );
        } catch (\Exception $e) {
            Logger::logError(
                TranslationUtility::__('Order with reference %s not found.', array($reference)),
                'Integration'
            );
        }
    }

    /**
     * Sets shipment price.
     *
     * @param string $reference
     * @param float $price
     */
    protected function setShipmentPrice($reference, $price)
    {
        try {
            $this->getOrderRepository()->setShippingPriceByReference($reference, $price);
        } catch (\Exception $e) {
            Logger::logError(
                TranslationUtility::__('Order with reference %s not found.', array($reference)),
                'Integration'
            );
        }
    }

    /**
     * Marks order with provided reference as deleted on the system.
     *
     * @param string $reference
     */
    protected function setDeleted($reference)
    {
        try {
            $this->getOrderRepository()->setDeleted($reference);
        } catch (\Exception $e) {
            Logger::logError(
                TranslationUtility::__('Order with reference %s not found.', array($reference)),
                'Integration'
            );
        }
    }

    /**
     * Returns array of orders that should be processed in this batch.
     *
     * @return array Batch of orders.
     */
    private function getBatchOrders()
    {
        return array_slice($this->ordersToSync, 0, $this->batchSize);
    }

    /**
     * Removes finished batch orders.
     */
    private function removeFinishedBatch()
    {
        $this->ordersToSync = array_slice($this->ordersToSync, $this->batchSize);
    }

    /**
     * Reports progress for a batch.
     */
    private function reportProgressForBatch()
    {
        $synced = $this->numberOfOrders - count($this->ordersToSync);
        $progressStep = $synced  * (100 - self::INITIAL_PROGRESS_PERCENT) / $this->numberOfOrders;
        $this->currentProgress = self::INITIAL_PROGRESS_PERCENT + $progressStep;
        $this->reportProgress($this->currentProgress);
    }

    /**
     * Returns an instance of order repository service.
     *
     * @return \Packlink\PrestaShop\Classes\Repositories\OrderRepository
     */
    private function getOrderRepository()
    {
        if ($this->orderRepository === null) {
            $this->orderRepository = ServiceRegister::getService(OrderRepository::CLASS_NAME);
        }

        return $this->orderRepository;
    }
}
