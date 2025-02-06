<?php


/** @noinspection PhpIncludeInspection */

use Packlink\BusinessLogic\Controllers\ManualRefreshServiceController as CoreController;
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
        PacklinkPrestaShopUtility::dieJson($this->controller->enqueueUpdateTask());
    }

    /**
     * @throws \Logeecom\Infrastructure\TaskExecution\Exceptions\QueueItemDeserializationException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryClassException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    public function displayAjaxGetTaskStatus()
    {
        PacklinkPrestaShopUtility::dieJson($this->controller->getTaskStatus());
    }
}
