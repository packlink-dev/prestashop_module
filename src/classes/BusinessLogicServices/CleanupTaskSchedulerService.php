<?php

namespace Packlink\PrestaShop\Classes\BusinessLogicServices;

use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\TaskExecution\LegacyTaskAdapter;
use Logeecom\Infrastructure\TaskExecution\QueueItem;
use Logeecom\Infrastructure\TaskExecution\Scheduler\ScheduleCheckTask;
use Logeecom\Infrastructure\TaskExecution\Tasks\TaskCleanupTask;
use Packlink\BusinessLogic\Scheduler\DTO\ScheduleConfig;
use Packlink\BusinessLogic\Scheduler\Interfaces\SchedulerInterface;

/**
 * Class CleanupTaskSchedulerService
 *
 * @package Packlink\PrestaShop\Classes\BusinessLogicServices
 */
class CleanupTaskSchedulerService
{
    /**
     * Schedules a new task in charge of deleting old schedule check tasks.
     *
     * Uses the core V2 scheduler contract. TaskCleanupTask is a legacy infrastructure task, so it is
     * wrapped in a LegacyTaskAdapter to be scheduled through the new SchedulerInterface.
     *
     * @return void
     */
    public static function scheduleTaskCleanupTask()
    {
        /** @var SchedulerInterface $scheduler */
        $scheduler = ServiceRegister::getService(SchedulerInterface::CLASS_NAME);

        $cleanupTask = new TaskCleanupTask(
            ScheduleCheckTask::getClassName(),
            array(QueueItem::COMPLETED),
            3600
        );

        $scheduler->scheduleHourly(
            new LegacyTaskAdapter($cleanupTask),
            new ScheduleConfig(0, 0, 10)
        );
    }
}
