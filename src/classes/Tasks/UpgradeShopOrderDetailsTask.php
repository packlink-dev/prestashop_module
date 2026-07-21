<?php

namespace Packlink\PrestaShop\Classes\Tasks;

use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\Utility\TimeProvider;
use Packlink\BusinessLogic\Http\DTO\Shipment;
use Packlink\BusinessLogic\Http\Proxy;
use Packlink\BusinessLogic\Order\OrderService;
use Packlink\BusinessLogic\OrderShipmentDetails\OrderShipmentDetailsService;
use Packlink\BusinessLogic\ShippingMethod\Utility\ShipmentStatus;
use Packlink\BusinessLogic\Tasks\Interfaces\BusinessTask;
use Packlink\BusinessLogic\Tasks\TaskExecutionConfig;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

/**
 * Class UpgradeShopOrderDetailsTask.
 *
 * Backfills shipment details (references, tracking, status) for orders created before the module
 * persisted them. Implements the core V2 business-task contract (BusinessTask): it reports progress
 * by yielding from execute() and is enqueued through the TaskExecutor.
 *
 * @package Packlink\PrestaShop\Classes\Tasks
 */
class UpgradeShopOrderDetailsTask implements BusinessTask
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
     * @var int|float
     */
    private $currentProgress;
    /**
     * @var TaskExecutionConfig|null
     */
    private $executionConfig;
    /**
     * @var OrderShipmentDetailsService
     */
    private $orderShipmentDetailsService;
    /**
     * @var OrderService
     */
    private $orderService;
    /**
     * @var Proxy
     */
    private $proxy;

    /**
     * UpgradeShopOrderDetailsTask constructor.
     *
     * @param array $oldOrders
     * @param TaskExecutionConfig|null $executionConfig
     */
    public function __construct(array $oldOrders, TaskExecutionConfig $executionConfig = null)
    {
        $this->ordersToSync = $oldOrders;
        $this->batchSize = self::DEFAULT_BATCH_SIZE;
        $this->numberOfOrders = count($this->ordersToSync);
        $this->currentProgress = self::INITIAL_PROGRESS_PERCENT;
        $this->executionConfig = $executionConfig;
    }

    /**
     * Transforms serializable object into an array.
     *
     * @return array Array representation of a serializable object.
     */
    public function toArray(): array
    {
        $data = array(
            'ordersToSync' => $this->ordersToSync,
            'batchSize' => $this->batchSize,
            'numberOfOrders' => $this->numberOfOrders,
            'currentProgress' => $this->currentProgress,
        );

        if ($this->executionConfig !== null) {
            $data['execution_config'] = $this->executionConfig->toArray();
        }

        return $data;
    }

    /**
     * Transforms array into a BusinessTask instance.
     *
     * @param array $data Data that is used to instantiate the task.
     *
     * @return BusinessTask
     */
    public static function fromArray(array $data): BusinessTask
    {
        $executionConfig = null;
        if (!empty($data['execution_config']) && is_array($data['execution_config'])) {
            $executionConfig = TaskExecutionConfig::fromArray($data['execution_config']);
        }

        $entity = new self($data['ordersToSync'], $executionConfig);
        $entity->batchSize = $data['batchSize'];
        $entity->numberOfOrders = $data['numberOfOrders'];
        $entity->currentProgress = $data['currentProgress'];

        return $entity;
    }

    /**
     * @inheritDoc
     */
    public function getExecutionConfig()
    {
        return $this->executionConfig;
    }

    /**
     * Executes the batch backfill, yielding progress to the task executor.
     *
     * @return \Generator
     */
    public function execute(): \Generator
    {
        yield $this->currentProgress;

        if ($this->numberOfOrders === 0) {
            yield 100;

            return;
        }

        /** @var TimeProvider $timeProvider */
        $timeProvider = ServiceRegister::getService(TimeProvider::CLASS_NAME);

        $count = count($this->ordersToSync);

        while ($count > 0) {
            $orders = $this->getBatchOrders();
            yield;

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
                    $shipment = $this->getProxy()->getShipment($order['draft_reference']);
                } catch (\Exception $e) {
                    $shipment = null;
                }

                if ($shipment !== null) {
                    $this->setShipmentStatusAndPrice($order['draft_reference'], $shipment);
                    $this->setTrackingInfo($order['draft_reference'], $shipment);
                } else {
                    $this->setDeleted($order['draft_reference']);
                }
            }

            $this->removeFinishedBatch();

            yield $this->calculateBatchProgress();

            $count = count($this->ordersToSync);
        }

        yield 100;
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
            $this->getOrderService()->setReference($orderId, $referenceId);
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
     * Sets tracking info for order.
     *
     * @param string $reference
     * @param Shipment $shipment
     */
    protected function setTrackingInfo($reference, $shipment)
    {
        try {
            $this->getOrderService()->updateTrackingInfo($shipment);
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
     * Sets order status and Packlink shipping price.
     *
     * @param string $reference
     * @param Shipment $shipment
     */
    protected function setShipmentStatusAndPrice($reference, $shipment)
    {
        try {
            $this->getOrderService()->updateShippingStatus(
                $shipment,
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
     * Marks order with provided reference as deleted on the system.
     *
     * @param string $reference
     */
    protected function setDeleted($reference)
    {
        try {
            $this->getOrderShipmentDetailsService()->markShipmentDeleted($reference);
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
     * Calculates progress after a processed batch.
     *
     * @return int|float
     */
    private function calculateBatchProgress()
    {
        $synced = $this->numberOfOrders - count($this->ordersToSync);
        $progressStep = $synced * (100 - self::INITIAL_PROGRESS_PERCENT) / $this->numberOfOrders;
        $this->currentProgress = self::INITIAL_PROGRESS_PERCENT + $progressStep;

        return $this->currentProgress;
    }

    /**
     * @return OrderService
     */
    private function getOrderService()
    {
        if ($this->orderService === null) {
            $this->orderService = ServiceRegister::getService(OrderService::CLASS_NAME);
        }

        return $this->orderService;
    }

    /**
     * @return OrderShipmentDetailsService
     */
    private function getOrderShipmentDetailsService()
    {
        if ($this->orderShipmentDetailsService === null) {
            $this->orderShipmentDetailsService = ServiceRegister::getService(OrderShipmentDetailsService::CLASS_NAME);
        }

        return $this->orderShipmentDetailsService;
    }

    /**
     * @return Proxy
     */
    private function getProxy()
    {
        if ($this->proxy === null) {
            $this->proxy = ServiceRegister::getService(Proxy::CLASS_NAME);
        }

        return $this->proxy;
    }
}
