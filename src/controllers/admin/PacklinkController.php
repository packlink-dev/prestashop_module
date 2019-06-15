<?php

/**
 * Class PacklinkController
 *
 * This controller is used to add Packlink PRO menu item to admin dashboard.
 */
class PacklinkController extends ModuleAdminController
{
    /**
     * PacklinkController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        Tools::redirectAdmin(
            Context::getContext()->link->getAdminLink('AdminModules')
            . '&configure=' . $this->module->name
            . '&tab_module=shipping_logistics&module_name=' . $this->module->name
        );
    }
}
