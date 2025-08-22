<?php

use Logeecom\Infrastructure\Configuration\Configuration;
use Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use Logeecom\Infrastructure\ServiceRegister;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;
use Packlink\BusinessLogic\Controllers\CashOnDeliveryController;
use Packlink\BusinessLogic\Controllers\ConfigurationController as BaseConfigurationController;

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

/**
 * Class ConfigurationController
 */
class ConfigurationController extends PacklinkBaseController
{
    /**
     * Returns data for the configuration page.
     */
    public function displayAjaxGetData()
    {
        $controller = new BaseConfigurationController();
        $service = ServiceRegister::getService(Configuration::CLASS_NAME);

        $cashOnDelivery = new CashOnDeliveryController();

        try {
            $hasSubscription = $cashOnDelivery->getAndUpdateSubscription();
        } catch (QueryFilterInvalidParamException $e) {
            $hasSubscription = false;
        }

        $data = array(
            'helpUrl' => $controller->getHelpLink(),
            'version' => $service->getModuleVersion(),
            'hasSubscription' => $hasSubscription
        );

        PacklinkPrestaShopUtility::dieJson($data);
    }
}
