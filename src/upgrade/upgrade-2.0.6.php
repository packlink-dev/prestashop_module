<?php

use Logeecom\Infrastructure\Configuration\Configuration;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Scheduler\Models\DailySchedule;
use Packlink\BusinessLogic\Scheduler\Models\HourlySchedule;
use Packlink\BusinessLogic\Scheduler\Models\Schedule;
use Packlink\BusinessLogic\ShippingMethod\Utility\ShipmentStatus;
use Packlink\BusinessLogic\Tasks\UpdateShipmentDataTask;
use Packlink\PrestaShop\Classes\Bootstrap;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Updates module to version 2.0.6.
 *
 * @param \Packlink $module
 *
 * @return boolean
 *
 * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
 * @throws \PrestaShopException
 */
function upgrade_module_2_0_6($module)
{
    $previousShopContext = \Shop::getContext();
    \Shop::setContext(\Shop::CONTEXT_ALL);

    Bootstrap::init();

    $configuration = ServiceRegister::getService(Configuration::CLASS_NAME);
    $repository = RepositoryRegistry::getRepository(Schedule::getClassName());

    $schedules = $repository->select();

    /** @var Schedule $schedule */
    foreach ($schedules as $schedule) {
        $task = $schedule->getTask();

        if ($task->getType() === UpdateShipmentDataTask::getClassName()) {
            $repository->delete($schedule);
        }
    }

    foreach (array(0, 30) as $minute) {
        $hourlyStatuses = array(
            ShipmentStatus::STATUS_PENDING,
        );

        $shipmentDataHalfHourSchedule = new HourlySchedule(
            new UpdateShipmentDataTask($hourlyStatuses),
            $configuration->getDefaultQueueName()
        );
        $shipmentDataHalfHourSchedule->setMinute($minute);
        $shipmentDataHalfHourSchedule->setNextSchedule();
        $repository->save($shipmentDataHalfHourSchedule);
    }

    $dailyStatuses = array(
        ShipmentStatus::STATUS_IN_TRANSIT,
        ShipmentStatus::STATUS_READY,
        ShipmentStatus::STATUS_ACCEPTED,
    );

    $dailyShipmentDataSchedule = new DailySchedule(
        new UpdateShipmentDataTask($dailyStatuses),
        $configuration->getDefaultQueueName()
    );

    $dailyShipmentDataSchedule->setHour(11);
    $dailyShipmentDataSchedule->setNextSchedule();

    $repository->save($dailyShipmentDataSchedule);

    $module->enable();

    \Shop::setContext($previousShopContext);

    return true;
}