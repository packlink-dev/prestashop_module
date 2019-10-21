<?php

use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;
use Packlink\PrestaShop\Classes\Utility\SystemInfoUtility;

class DebugController extends PacklinkBaseController
{
    const SYSTEM_INFO_FILE_NAME = 'packlink-debug-data.zip';

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

    /**
     * Retrieves debug mode status.
     */
    public function displayAjaxGetStatus()
    {
        PacklinkPrestaShopUtility::dieJson(array('status' => $this->getConfigService()->isDebugModeEnabled()));
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

        $this->getConfigService()->setDebugModeEnabled($data['status']);

        PacklinkPrestaShopUtility::dieJson(array('status' => $data['status']));
    }
}
