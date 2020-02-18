<?php

use Logeecom\Infrastructure\Configuration\Configuration;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\TaskExecution\QueueItem;
use Logeecom\Infrastructure\TaskExecution\QueueService;
use Packlink\BusinessLogic\Scheduler\Models\HourlySchedule;
use Packlink\BusinessLogic\Scheduler\Models\Schedule;
use Packlink\BusinessLogic\Scheduler\ScheduleCheckTask;
use Packlink\BusinessLogic\Tasks\TaskCleanupTask;
use Packlink\BusinessLogic\Tasks\UpdateShippingServicesTask;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\BusinessLogic\OrderShipmentDetails\Models\OrderShipmentDetails;
use Packlink\PrestaShop\Classes\Repositories\BaseRepository;
use Packlink\BusinessLogic\ShipmentDraft\Models\OrderSendDraftTaskMap;
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
 */
function upgrade_module_2_2_0($module)
{
    $previousShopContext = \Shop::getContext();
    \Shop::setContext(\Shop::CONTEXT_ALL);

    Bootstrap::init();

    clearCompletedSchedulers();
    migrateShopOrderDetailEntities();
    updateServices();
    removeOrdersColumn();

    $module->enable();

    \Shop::setContext($previousShopContext);

    return true;
}

/**
 * Schedules a new task in charge of deleting old schedule check tasks.
 *
 * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
 */
function clearCompletedSchedulers()
{
    $configuration = ServiceRegister::getService(Configuration::CLASS_NAME);
    $scheduleRepository = RepositoryRegistry::getRepository(Schedule::getClassName());

    $schedule = new HourlySchedule(
        new TaskCleanupTask(ScheduleCheckTask::getClassName(), array(QueueItem::COMPLETED), 3600),
        $configuration->getDefaultQueueName()
    );

    $schedule->setMinute(10);
    $schedule->setNextSchedule();
    $scheduleRepository->save($schedule);
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

    $records = \Db::getInstance()->executeS($query);
    if (!empty($records)) {
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
    /** @var \Logeecom\Infrastructure\TaskExecution\QueueService $queueService */
    $queueService = ServiceRegister::getService(QueueService::CLASS_NAME);
    /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService $configService */
    $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
    if ($queueService->findLatestByType('UpdateShippingServicesTask') !== null) {
        $queueService->enqueue($configService->getDefaultQueueName(), new UpdateShippingServicesTask());
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
    } catch (\PrestaShopException $e) {
        $this->tryLogError('Error removing orders table column. Error: ' . $e->getMessage());

        return false;
    }

    return true;
}