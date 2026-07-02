<?php

use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;
use Packlink\BusinessLogic\Controllers\SubscriptionController as BaseSubscriptionController;

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

/**
 * Class SubscriptionController
 */
class SubscriptionController extends PacklinkBaseController
{
    /**
     * Returns the merchant's subscription plan tier and display name.
     */
    public function displayAjaxGetPlan()
    {
        $controller = new BaseSubscriptionController();

        PacklinkPrestaShopUtility::dieJson($controller->getPlan()->toArray());
    }

    /**
     * Returns promotional banner data for the shipping services page.
     */
    public function displayAjaxGetPromotionalBanner()
    {
        $controller = new BaseSubscriptionController();

        PacklinkPrestaShopUtility::dieJson($controller->getPromotionalBanner()->toArray());
    }
}
