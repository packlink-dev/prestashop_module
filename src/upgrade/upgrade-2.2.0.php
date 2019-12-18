<?php

use Logeecom\Infrastructure\Configuration\Configuration;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\TaskExecution\QueueItem;
use Packlink\BusinessLogic\Scheduler\Models\HourlySchedule;
use Packlink\BusinessLogic\Scheduler\Models\Schedule;
use Packlink\BusinessLogic\Scheduler\ScheduleCheckTask;
use Packlink\BusinessLogic\Tasks\TaskCleanupTask;
use Packlink\PrestaShop\Classes\Bootstrap;

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
 * @throws \PrestaShopException
 */
function upgrade_module_2_2_0($module)
{
    $previousShopContext = \Shop::getContext();
    \Shop::setContext(\Shop::CONTEXT_ALL);

    Bootstrap::init();

    $configuration = ServiceRegister::getService(Configuration::CLASS_NAME);
    $repository = RepositoryRegistry::getRepository(Schedule::getClassName());

    $schedule = new HourlySchedule(
        new TaskCleanupTask(ScheduleCheckTask::getClassName(), array(QueueItem::COMPLETED), 3600),
        $configuration->getDefaultQueueName()
    );

    $schedule->setMinute(10);
    $schedule->setNextSchedule();
    $repository->save($schedule);

    $module->enable();

    \Shop::setContext($previousShopContext);

    return true;
}