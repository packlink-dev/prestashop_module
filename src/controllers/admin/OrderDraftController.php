<?php

use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\TaskExecution\QueueService;
use Packlink\BusinessLogic\Configuration as ConfigurationInterface;
use Packlink\BusinessLogic\Order\Interfaces\OrderRepository as OrderRepositoryInterface;
use Packlink\BusinessLogic\Order\Models\OrderShipmentDetails;
use Packlink\BusinessLogic\Tasks\SendDraftTask;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService;
use Packlink\PrestaShop\Classes\Repositories\OrderRepository;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

/**
 * Class OrderDraftController
 */
class OrderDraftController extends ModuleAdminController
{
    /**
     * ShipmentLabelsController constructor.
     *
     * @throws \PrestaShopException
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();

        $this->bootstrap = true;
    }

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
            Bootstrap::init();
            /** @var ConfigurationService $configService */
            $configService = ServiceRegister::getService(ConfigurationInterface::CLASS_NAME);
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

                $queue->enqueue($configService->getDefaultQueueName(), $draftTask);

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
