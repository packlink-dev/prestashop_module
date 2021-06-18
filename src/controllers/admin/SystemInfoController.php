<?php

use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;
use Packlink\BusinessLogic\Controllers\SystemInfoController as BaseSystemInfoController;

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

/**
 * Class SystemInfoController
 */
class SystemInfoController extends PacklinkBaseController
{
    /**
     * Returns regions available for Packlink account registration.
     */
    public function displayAjaxGet()
    {
        $controller = new BaseSystemInfoController();

        PacklinkPrestaShopUtility::dieDtoEntities($controller->get());
    }
}
