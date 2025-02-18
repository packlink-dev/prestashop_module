<?php
/**
 * 2025 Packlink
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Apache License 2.0
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * http://www.apache.org/licenses/LICENSE-2.0.txt
 *
 * @author    Packlink <support@packlink.com>
 * @copyright 2025 Packlink Shipping S.L
 * @license   http://www.apache.org/licenses/LICENSE-2.0.txt  Apache License 2.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}


/** @noinspection PhpIncludeInspection */

use Packlink\BusinessLogic\Controllers\ManualRefreshController as CoreController;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

/**
 * Class ManualRefreshServiceController
 */
class ManualRefreshServiceController extends PacklinkBaseController
{
    /**
     * @var \Packlink\BusinessLogic\Controllers\ManualRefreshServiceController
     */
    protected $controller;

    public function __construct()
    {
        parent::__construct();

        $this->controller = new CoreController();
    }

    public function displayAjaxRefreshService()
    {
        PacklinkPrestaShopUtility::dieJson($this->controller->enqueueUpdateTask()->toArray());
    }

    /**
     * @throws \Logeecom\Infrastructure\TaskExecution\Exceptions\QueueItemDeserializationException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryClassException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    public function displayAjaxGetTaskStatus()
    {
        PacklinkPrestaShopUtility::dieJson($this->controller->getTaskStatus()->toArray());
    }
}
