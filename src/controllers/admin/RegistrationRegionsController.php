<?php

use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;
use Packlink\BusinessLogic\Controllers\RegistrationRegionsController as BaseRegistrationRegionsController;

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

/**
 * Class RegistrationRegionsController
 */
class RegistrationRegionsController extends PacklinkBaseController
{
    /**
     * Returns regions available for Packlink account registration.
     */
    public function displayAjaxGetRegions()
    {
        $controller = new BaseRegistrationRegionsController();

        PacklinkPrestaShopUtility::dieDtoEntities($controller->getRegions());
    }
}
