<?php

use Logeecom\Infrastructure\AutoTest\AutoTestService;
use Logeecom\Infrastructure\Configuration\Configuration as PacklinkConfiguration;
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
        $autoTest = Tools::getValue('auto-test');

        if ($autoTest !== false) {
            $autoTestService = new AutoTestService();
            $autoTestService->setAutoTestMode();
            Logger::logInfo('Received auto-test async process request.', 'Integration', array('guid' => $guid));
        } else {
            Logger::logDebug('Received async process request.', 'Integration', array('guid' => $guid));
        }

        if ($guid !== 'auto-configure') {
            /** @var AsyncProcessStarterService $asyncProcessService */
            $asyncProcessService = ServiceRegister::getService(AsyncProcessService::CLASS_NAME);
            $asyncProcessService->runProcess($guid);
        }

        PacklinkPrestaShopUtility::dieJson(array('success' => true));
    }

    /**
     * Initializes AsyncProcess controller.
     */
    public function init()
    {
        /** @var PacklinkConfiguration $configService */
        $configService = ServiceRegister::getService(PacklinkConfiguration::CLASS_NAME);
        if ($configService->isDebugModeEnabled()) {
            error_reporting(E_WARNING);
            ini_set('display_errors', true);
        }

        try {
            parent::init();
        } catch (\Exception $e) {
            Logger::logWarning(
                'Error initializing AsyncProcessController',
                'Integration',
                array(
                    'Message' => $e->getMessage(),
                    'Stack trace' => $e->getTraceAsString(),
                )
            );
        }
    }

    /**
     * Displays maintenance page if shop is closed.
     */
    public function displayMaintenancePage()
    {
        // allow async process in maintenance mode
    }

    /**
     * Displays 'country restricted' page if user's country is not allowed.
     */
    protected function displayRestrictedCountryPage()
    {
        // allow async process
    }
}
