<?php

use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Configuration;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;
use Packlink\PrestaShop\Classes\Utility\SystemInfoUtility;

class DebugController extends ModuleAdminController
{
    const SYSTEM_INFO_FILE_NAME = 'packlink-debug-data.zip';
    /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService */
    protected $configService;

    /**
     * DebugController constructor.
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
     * Downloads system info zip file.
     */
    public function displayAjaxGetSystemInfo()
    {
        $file = SystemInfoUtility::getSystemInfo();

        PacklinkPrestaShopUtility::dieFile($file, self::SYSTEM_INFO_FILE_NAME);
    }

    /**
     * Retrieves debug mode status.
     */
    public function displayAjaxGetStatus()
    {
        $result = array(
            'status' => $this->getConfig()->isDebugModeEnabled(),
        );

        PacklinkPrestaShopUtility::dieJson($result);
    }

    /**
     * Sets debug mode status.
     */
    public function displayAjaxSetStatus()
    {
        $data = PacklinkPrestaShopUtility::getPacklinkPostData();
        if (!isset($data['status']) || !is_bool($data['status'])) {
            PacklinkPrestaShopUtility::die400();
        }

        $this->getConfig()->setDebugModeEnabled($data['status']);
        PacklinkPrestaShopUtility::dieJson(array('status' => $data['status']));
    }

    /**
     * Retrieves config service.
     *
     * @return \Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService
     */
    protected function getConfig()
    {
        if ($this->configService === null) {
            $this->configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        }

        return $this->configService;
    }
}
