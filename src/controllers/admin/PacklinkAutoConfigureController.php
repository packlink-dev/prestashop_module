<?php

use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Controllers\AutoConfigurationController;
use Packlink\BusinessLogic\UpdateShippingServices\Interfaces\UpdateShippingServicesOrchestratorInterface;
use Packlink\BusinessLogic\UpdateShippingServices\Interfaces\UpdateShippingServiceTaskStatusServiceInterface;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

/**
 * Class PacklinkAutoConfigureController.
 */
class PacklinkAutoConfigureController extends PacklinkBaseController
{
    /**
     * Starts the auto-configuration.
     */
    public function initContent()
    {
        /** @var UpdateShippingServicesOrchestratorInterface $orchestrator */
        $orchestrator = ServiceRegister::getService(UpdateShippingServicesOrchestratorInterface::class);
        /** @var UpdateShippingServiceTaskStatusServiceInterface $service */
        $service = ServiceRegister::getService(UpdateShippingServiceTaskStatusServiceInterface::class);

        $controller = new AutoConfigurationController($orchestrator, $service);

        PacklinkPrestaShopUtility::dieJson(array('success' => $controller->start(true)));
    }
}
