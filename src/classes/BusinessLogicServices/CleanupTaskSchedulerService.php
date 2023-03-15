<?php

namespace Packlink\PrestaShop\Classes\BusinessLogicServices;

use Logeecom\Infrastructure\Configuration\Configuration;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\TaskExecution\QueueItem;
use Packlink\BusinessLogic\Scheduler\Models\HourlySchedule;
use Packlink\BusinessLogic\Scheduler\Models\Schedule;
use Packlink\BusinessLogic\Scheduler\ScheduleCheckTask;
use Packlink\BusinessLogic\Tasks\TaskCleanupTask;

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
     * @return void
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public static function scheduleTaskCleanupTask()
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
}
