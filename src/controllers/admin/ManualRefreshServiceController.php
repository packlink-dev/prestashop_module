<?php

use Packlink\BusinessLogic\Controllers\ManualRefreshController as CoreController;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

/**
 * Class ManualRefreshServiceController
 */
class ManualRefreshServiceController extends PacklinkBaseController
{
    /**
     * @var \Packlink\BusinessLogic\Controllers\ManualRefreshController
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
