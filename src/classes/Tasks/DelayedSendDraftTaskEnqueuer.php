<?php

namespace Packlink\PrestaShop\Classes\Tasks;

use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Logeecom\Infrastructure\Serializer\Serializer;
use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\TaskExecution\QueueService;
use Logeecom\Infrastructure\TaskExecution\Task;
use Packlink\BusinessLogic\Order\Interfaces\OrderRepository;
use Packlink\BusinessLogic\Order\Models\OrderShipmentDetails;
use Packlink\BusinessLogic\Tasks\SendDraftTask;

/**
 * Class DelayedSendDraftTaskEnqueuer
 *
 * This task should be enqueued with a delay when send draft task must be enqueued with delay.
 *
 * @package Packlink\PrestaShop\Classes\Tasks
 */
class DelayedSendDraftTaskEnqueuer extends Task
{
    /**
     * @var int Shop order id.
     */
    protected $orderId;
    /**
     * @var \Packlink\PrestaShop\Classes\Repositories\OrderRepository
     */
    protected $orderRepository;
    /**
     * @var \Logeecom\Infrastructure\TaskExecution\QueueService
     */
    protected $queue;
    /**
     * @var \PrestaShop\PrestaShop\Core\Repository\RepositoryInterface
     */
    protected $orderDetailsRepository;

    /**
     * DelaySendDraftTaskTask constructor.
     *
     * @param int $orderId Shop order id.
     */
    public function __construct($orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * Runs task logic.
     *
     * @throws \Logeecom\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function execute()
    {
        $orderDetails = $this->getOrderDetails();

        if (!$orderDetails) {
            Logger::logError("Failed to retrieve order details for order [{$this->orderId}].");
            $this->reportProgress(100);

            return;
        }

        $task = new SendDraftTask($this->orderId);
        $this->getQueue()->enqueue($this->getConfigService()->getDefaultQueueName(), $task);
        if ($task->getExecutionId() !== null) {
            $orderDetails = $this->getOrderDetails();
            if ($orderDetails) {
                $orderDetails->setTaskId($task->getExecutionId());
                $this->getOrderDetailsRepository()->update($orderDetails);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function serialize()
    {
        return Serializer::serialize(array($this->orderId));
    }

    /**
     * @inheritDoc
     */
    public function unserialize($serialized)
    {
        list($this->orderId) = Serializer::unserialize($serialized);
    }

    /**
     * Transforms array into an serializable object,
     *
     * @param array $array Data that is used to instantiate serializable object.
     *
     * @return \Logeecom\Infrastructure\Serializer\Interfaces\Serializable
     *      Instance of serialized object.
     */
    public static function fromArray(array $array)
    {
        return new static($array['orderId']);
    }

    /**
     * Transforms serializable object into an array.
     *
     * @return array Array representation of a serializable object.
     */
    public function toArray()
    {
        return array('orderId' => $this->orderId);
    }

    /**
     * Retrieves order details.
     *
     * @return \Packlink\BusinessLogic\Order\Models\OrderShipmentDetails | null
     */
    private function getOrderDetails()
    {
        try {
            $orderDetails = $this->getOrderRepository()->getOrderDetailsById($this->orderId);
        } catch (\Exception $e) {
            $orderDetails = null;
        }

        return $orderDetails;
    }

    /**
     * Retrieves order repository.
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

    /**
     * Retrieves queue.
     *
     * @return \Logeecom\Infrastructure\TaskExecution\QueueService
     */
    private function getQueue()
    {
        if ($this->queue === null) {
            $this->queue = ServiceRegister::getService(QueueService::CLASS_NAME);
        }

        return $this->queue;
    }

    /**
     * Retrieves order details repository.
     *
     * @return \Logeecom\Infrastructure\ORM\Interfaces\RepositoryInterface
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