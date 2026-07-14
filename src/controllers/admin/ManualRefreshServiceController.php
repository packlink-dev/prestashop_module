<?php

use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Controllers\ManualRefreshController as CoreController;
use Packlink\BusinessLogic\UpdateShippingServices\Interfaces\UpdateShippingServicesOrchestratorInterface;
use Packlink\BusinessLogic\UpdateShippingServices\Interfaces\UpdateShippingServiceTaskStatusServiceInterface;
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

        /** @var UpdateShippingServiceTaskStatusServiceInterface $statusService */
        $statusService = ServiceRegister::getService(UpdateShippingServiceTaskStatusServiceInterface::class);
        /** @var UpdateShippingServicesOrchestratorInterface $orchestrator */
        $orchestrator = ServiceRegister::getService(UpdateShippingServicesOrchestratorInterface::class);

        $this->controller = new CoreController($statusService, $orchestrator);
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
