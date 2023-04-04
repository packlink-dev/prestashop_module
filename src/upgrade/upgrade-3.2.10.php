<?php

use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\BusinessLogicServices\CleanupTaskSchedulerService;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Updates module to version 3.2.10.
 *
 * @return bool
 * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
 */
function upgrade_module_3_2_10()
{
    $previousShopContext = \Shop::getContext();
    \Shop::setContext(\Shop::CONTEXT_ALL);

    Bootstrap::init();
    CleanupTaskSchedulerService::scheduleTaskCleanupTask();

    \Shop::setContext($previousShopContext);

    return true;
}
