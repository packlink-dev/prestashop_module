<?php

use Packlink\PrestaShop\Classes\Bootstrap;

/**
 * Class DashboardController
 */
class DashboardController extends ModuleAdminController
{
    /**
     * DashboardController constructor.
     *
     * @throws \PrestaShopException
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();

        $this->bootstrap = true;
    }

    /**
     * Retrieves current setup status.
     */
    public function displayAjaxGetStatus()
    {
        $controller = new \Packlink\BusinessLogic\Controllers\DashboardController();
        $result = $controller->getStatus();

        die(json_encode($result->toArray()));
    }
}
