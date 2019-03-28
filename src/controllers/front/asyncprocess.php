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

use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\TaskExecution\AsyncProcessStarterService;
use Logeecom\Infrastructure\TaskExecution\Interfaces\AsyncProcessService;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

/** @noinspection AutoloadingIssuesInspection */

/**
 * Class PacklinkAsyncProcessModuleFrontController
 */
class PacklinkAsyncProcessModuleFrontController extends ModuleFrontController
{
    /**
     * PacklinkAsyncProcessModuleFrontController constructor.
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();
    }

    /**
     * Starts process asynchronously.
     */
    public function initContent()
    {
        $guid = trim(Tools::getValue('guid'));
        Logger::logDebug('Received async process request.', 'Integration', array('guid' => $guid));

        /** @var AsyncProcessStarterService $asyncProcessService */
        $asyncProcessService = ServiceRegister::getService(AsyncProcessService::CLASS_NAME);
        $asyncProcessService->runProcess($guid);

        PacklinkPrestaShopUtility::dieJson(array('success' => true));
    }
}
