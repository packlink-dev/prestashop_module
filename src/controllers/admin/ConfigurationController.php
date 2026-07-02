<?php

use Logeecom\Infrastructure\Configuration\Configuration;
use Logeecom\Infrastructure\ServiceRegister;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;
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

        $userInfo = $service->getUserInfo();

        $data = array(
            'helpUrl' => $controller->getHelpLink(),
            'version' => $service->getModuleVersion(),
            'email' => $userInfo !== null ? $userInfo->email : '',
        );

        PacklinkPrestaShopUtility::dieJson($data);
    }
}
