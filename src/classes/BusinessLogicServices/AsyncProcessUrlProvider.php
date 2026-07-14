<?php

namespace Packlink\PrestaShop\Classes\BusinessLogicServices;

use Logeecom\Infrastructure\TaskExecution\Interfaces\AsyncProcessUrlProviderInterface;

/**
 * Class AsyncProcessUrlProvider.
 *
 * Provides the public URL of the module's async-process front controller. Under core V2 the
 * task-runner stack resolves the async-process endpoint through this contract instead of the
 * Configuration service, so PrestaShop (which runs in Standalone / TaskRunner mode) supplies it here.
 *
 * @package Packlink\PrestaShop\Classes\BusinessLogicServices
 */
class AsyncProcessUrlProvider implements AsyncProcessUrlProviderInterface
{
    /**
     * @inheritDoc
     */
    public function getAsyncProcessUrl($guid)
    {
        return ConfigurationService::getInstance()->getAsyncProcessUrl($guid);
    }
}
