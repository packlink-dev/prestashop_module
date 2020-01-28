<?php

use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

/**
 * Class DashboardController
 */
class DashboardController extends PacklinkBaseController
{
    /**
     * Retrieves current setup status.
     *
     * @throws \Packlink\BusinessLogic\DTO\Exceptions\FrontDtoNotRegisteredException
     */
    public function displayAjaxGetStatus()
    {
        $controller = new \Packlink\BusinessLogic\Controllers\DashboardController();

        try {
            $status = $controller->getStatus();
            PacklinkPrestaShopUtility::dieJson($status->toArray());
        } catch (\Packlink\BusinessLogic\DTO\Exceptions\FrontDtoValidationException $e) {
            PacklinkPrestaShopUtility::die400WithValidationErrors($e->getValidationErrors());
        }
    }
}
