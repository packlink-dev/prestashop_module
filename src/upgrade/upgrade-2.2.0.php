<?php

use Logeecom\Infrastructure\Configuration\Configuration;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\TaskExecutor\Interfaces\TaskExecutorInterface;
use Logeecom\Infrastructure\TaskExecutor\Interfaces\TaskStatusProviderInterface;
use Logeecom\Infrastructure\TaskExecutor\Model\TaskStatus;
use Packlink\BusinessLogic\OrderShipmentDetails\Models\OrderShipmentDetails;
use Packlink\BusinessLogic\ShipmentDraft\Models\OrderSendDraftTaskMap;
use Packlink\BusinessLogic\Tasks\BusinessTasks\UpdateShippingServicesBusinessTask;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\BusinessLogicServices\CleanupTaskSchedulerService;
use Packlink\PrestaShop\Classes\Repositories\BaseRepository;
use Packlink\PrestaShop\Classes\Repositories\OrderRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Updates module to version 2.2.0.
 *
 * @param \Packlink $module
 *
 * @return boolean
 *
 * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
 * @throws \Logeecom\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
 * @throws \PrestaShopException
 * @noinspection PhpUnused
 */
function upgrade_module_2_2_0($module)
{
    $previousShopContext = \Shop::getContext();
    $previousShopId = \Shop::getContextShopID();
    $previousGroupId = \Shop::getContextShopGroupID(true);
    \Shop::setContext(\Shop::CONTEXT_ALL);

    Bootstrap::init();

    clearCompletedSchedulers();
    migrateShopOrderDetailEntities();
    updateServices();
    removeOrdersColumn();

    $module->enable();

    if ($previousShopContext === \Shop::CONTEXT_SHOP) {
        \Shop::setContext(\Shop::CONTEXT_SHOP, $previousShopId);
    } elseif ($previousShopContext === \Shop::CONTEXT_GROUP) {
        \Shop::setContext(\Shop::CONTEXT_GROUP, $previousGroupId);
    } else {
        \Shop::setContext(\Shop::CONTEXT_ALL);
    }

    return true;
}

/**
 * Schedules a new task in charge of deleting old schedule check tasks.
 *
 * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
 */
function clearCompletedSchedulers()
{
    // Core V2 scheduler contract; TaskCleanupTask is scheduled via the shared service.
    CleanupTaskSchedulerService::scheduleTaskCleanupTask();
}

/**
 * Migrates old shop order details entities.
 *
 * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
 */
function migrateShopOrderDetailEntities()
{
    $query = new \DbQuery();
    $query->select('*')
        ->from(bqSQL(BaseRepository::TABLE_NAME))
        ->where('`type` = "OrderShipmentDetails"');

    try {
        $records = \Db::getInstance()->executeS($query);
    } catch (PrestaShopDatabaseException $e) {
    }

    if (!empty($records)) {
        /** @var Configuration $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);

        $userInfo = $configService->getUserInfo();
        $userDomain = 'com';
        if ($userInfo && in_array($userInfo->country, array('ES', 'DE', 'FR', 'IT'))) {
            $userDomain = \Tools::strtolower($userInfo->country);
        }

        $baseShipmentUrl = "https://pro.packlink.$userDomain/private/shipments/";

        $orderShipmentDetailsRepository = RepositoryRegistry::getRepository(OrderShipmentDetails::getClassName());
        $orderSendDraftRepository = RepositoryRegistry::getRepository(OrderSendDraftTaskMap::getClassName());

        foreach ($records as $record) {
            $orderShipmentData = json_decode($record['data'], true);

            $orderSendDraftTaskMap = new OrderSendDraftTaskMap();
            $orderSendDraftTaskMap->setOrderId((string)$orderShipmentData['orderId']);
            $orderSendDraftTaskMap->setExecutionId($orderShipmentData['taskId']);
            $orderSendDraftRepository->save($orderSendDraftTaskMap);

            unset($orderShipmentData['taskId']);
            $orderShipmentDetails = OrderShipmentDetails::fromArray($orderShipmentData);
            $orderShipmentDetails->setOrderId((string)$orderShipmentData['orderId']);
            $orderShipmentDetails->setShipmentUrl($baseShipmentUrl . $orderShipmentDetails->getReference());

            $orderShipmentDetailsRepository->update($orderShipmentDetails);
        }
    }
}

/**
 * Updates Packlink services.
 *
 * @throws \Logeecom\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
 */
function updateServices()
{
    /** @var TaskStatusProviderInterface $statusProvider */
    $statusProvider = ServiceRegister::getService(TaskStatusProviderInterface::CLASS_NAME);
    /** @var TaskExecutorInterface $taskExecutor */
    $taskExecutor = ServiceRegister::getService(TaskExecutorInterface::CLASS_NAME);

    // Re-enqueue the service refresh only for stores that had it before (legacy queue-item type).
    $status = $statusProvider->getLatestStatus('UpdateShippingServicesTask');
    if ($status->getStatus() !== TaskStatus::NOT_FOUND) {
        $taskExecutor->enqueue(new UpdateShippingServicesBusinessTask());
    }
}

/**
 * Removes Packlink shipment reference column from orders table.
 *
 * @return bool
 */
function removeOrdersColumn()
{
    try {
        $sql = 'ALTER TABLE ' . bqSQL(_DB_PREFIX_ . 'orders')
            . ' DROP COLUMN ' . bqSQL(OrderRepository::PACKLINK_ORDER_DRAFT_FIELD);

        \Db::getInstance()->execute($sql);
    } catch (\Exception $e) {
        return false;
    }

    return true;
}
