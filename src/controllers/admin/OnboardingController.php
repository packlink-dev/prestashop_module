<?php

use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;
use Packlink\BusinessLogic\Controllers\OnboardingController as BaseOnboardingController;

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

/**
 * Class OnboardingController
 */
class OnboardingController extends PacklinkBaseController
{
    /**
     * Returns the current state of the on-boarding page.
     */
    public function displayAjaxGetCurrentState()
    {
        $controller = new BaseOnboardingController();

        PacklinkPrestaShopUtility::dieJson($controller->getCurrentState()->toArray());
    }
}
