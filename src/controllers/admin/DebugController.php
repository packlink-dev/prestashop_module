<?php
/**
 * 2019 Packlink
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    Packlink <support@packlink.com>
 * @copyright 2019 Packlink Shipping S.L
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

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
