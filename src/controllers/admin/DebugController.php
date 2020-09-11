<?php

use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;
use Packlink\PrestaShop\Classes\Utility\SystemInfoUtility;
use Packlink\BusinessLogic\Controllers\DebugController as BaseDebugController;

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

class DebugController extends PacklinkBaseController
{
    const SYSTEM_INFO_FILE_NAME = 'packlink-debug-data.zip';

    /** @var BaseDebugController */
    private $baseController;

    public function __construct()
    {
        parent::__construct();

        $this->baseController = new BaseDebugController();
    }

    /**
     * Retrieves debug mode status.
     *
     * @throws \PrestaShopException
     */
    public function displayAjaxGetStatus()
    {
        PacklinkPrestaShopUtility::dieJson(array(
            'status' => $this->baseController->getStatus(),
            'downloadUrl' => $this->getAction('Debug', 'getSystemInfo'),
        ));
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

        $this->baseController->setStatus($data['status']);

        PacklinkPrestaShopUtility::dieJson(array('status' => $data['status']));
    }

    /**
     * Downloads system info zip file.
     *
     * @throws \PrestaShopException
     */
    public function displayAjaxGetSystemInfo()
    {
        $file = SystemInfoUtility::getSystemInfo();

        PacklinkPrestaShopUtility::dieFile($file, self::SYSTEM_INFO_FILE_NAME);
    }
}
