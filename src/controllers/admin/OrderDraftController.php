<?php

use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\TaskExecution\QueueService;
use Packlink\BusinessLogic\Order\Interfaces\OrderRepository as OrderRepositoryInterface;
use Packlink\BusinessLogic\Order\Models\OrderShipmentDetails;
use Packlink\BusinessLogic\Tasks\SendDraftTask;
use Packlink\PrestaShop\Classes\Repositories\OrderRepository;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

/**
 * Class OrderDraftController
 */
class OrderDraftController extends PacklinkBaseController
{
    /**
     * Creates order draft for order identified by ID in the request by enqueuing SendDraftTask.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function displayAjaxCreateOrderDraft()
    {
        $data = PacklinkPrestaShopUtility::getPacklinkPostData();

        if ($data['orderId']) {
            $orderId = $data['orderId'];
            /** @var QueueService $queue */
            $queue = ServiceRegister::getService(QueueService::CLASS_NAME);
            /** @var OrderRepository $orderRepository */
            $orderRepository = ServiceRegister::getService(OrderRepositoryInterface::CLASS_NAME);

            $orderDetails = $orderRepository->getOrderDetailsById($orderId);
            if ($orderDetails === null) {
                $orderDetails = new OrderShipmentDetails();
                $orderDetails->setOrderId($orderId);
                $orderRepository->saveOrderDetails($orderDetails);
            }

            try {
                $draftTask = new SendDraftTask($orderId);

                $queue->enqueue($this->getConfigService()->getDefaultQueueName(), $draftTask);

                if ($draftTask->getExecutionId() !== null) {
                    $orderDetails->setTaskId($draftTask->getExecutionId());
                    $orderRepository->saveOrderDetails($orderDetails);
                }
            } catch (\Logeecom\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException $e) {
                PacklinkPrestaShopUtility::die500(array(
                    'success' => false,
                    'message' => $e->getMessage(),
                ));
            }

            PacklinkPrestaShopUtility::dieJson(array('success' => true));
        }

        PacklinkPrestaShopUtility::die400(array('message' => 'Order ID missing'));
    }
}
