<?php

use Logeecom\Infrastructure\AutoTest\AutoTestLogger;
use Logeecom\Infrastructure\AutoTest\AutoTestService;
use Logeecom\Infrastructure\Exceptions\StorageNotAccessibleException;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\InfrastructureServices\LoggerService;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

/**
 * Class PacklinkAutoTestController.
 */
class PacklinkAutoTestController extends ModuleAdminController
{
    /**
     * PacklinkAutoTestController constructor.
     *
     * @throws \PrestaShopException
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();

        $this->bootstrap = true;

        $this->page_header_toolbar_title = $this->l('PacklinkPRO module auto-test', 'packlink');
        $this->meta_title = $this->page_header_toolbar_title;
    }

    /**
     * Controller entry endpoint.
     *
     * @throws \SmartyException
     * @throws \PrestaShopException
     */
    public function initContent()
    {
        $action = Tools::getValue('action');

        if (!$action) {
            $this->displayPage();

            return;
        }

        if (method_exists($this, $action)) {
            $this->$action();
        }

        PacklinkPrestaShopUtility::die400();
    }

    /**
     * Displays main Auto-test page.
     *
     * @throws \SmartyException
     * @throws \PrestaShopException
     */
    protected function displayPage()
    {
        parent::initContent();

        $path = $this->module->getPathUri();
        $this->addCSS(
            array(
                $path . 'views/css/auto-test.css',
                $path . 'views/css/packlink.css',
                $path . 'views/css/bootstrap-prestashop-ui-kit.css',
            )
        );
        $this->addJS(
            array(
                $path . 'views/js/core/UtilityService.js',
                $path . 'views/js/core/TemplateService.js',
                $path . 'views/js/core/AjaxService.js',
                $path . 'views/js/core/AutoTestController.js',
                $path . 'views/js/PrestaFix.js',
            )
        );

        // assign template variables
        $this->context->smarty->assign(
            array(
                'dashboardLogo' => $path . 'views/img/logo-pl.svg',
                'startTestUrl' => $this->getAction('PacklinkAutoTest', 'start'),
                'checkStatusUrl' => $this->getAction('PacklinkAutoTest', 'checkStatus'),
                'downloadLogUrl' => $this->getAction('PacklinkAutoTest', 'exportLogs', false),
                'systemInfoUrl' => $this->getAction('Debug', 'getSystemInfo', false),
                'moduleUrl' => $this->getAction('Packlink', '', false),
            )
        );
        // render template and assign it to the page
        $content = $this->context->smarty->fetch($this->getTemplatePath() . 'auto-test.tpl');
        $this->context->smarty->assign(
            array(
                'content' => $content,
            )
        );
    }

    /**
     * Runs the auto-test and returns the queue item ID.
     *
     * @throws \Logeecom\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
     */
    protected function start()
    {
        $service = new AutoTestService();
        try {
            PacklinkPrestaShopUtility::dieJson(array('success' => true, 'itemId' => $service->startAutoTest()));
        } catch (StorageNotAccessibleException $e) {
            PacklinkPrestaShopUtility::dieJson(
                array(
                    'success' => false,
                    'error' => TranslationUtility::__('Database not accessible.'),
                )
            );
        }
    }

    /**
     * Checks the status of the auto-test task.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryClassException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    protected function checkStatus()
    {
        $service = new AutoTestService();
        $status = $service->getAutoTestTaskStatus(Tools::getValue('queueItemId', 0));

        if ($status->finished) {
            $service->stopAutoTestMode(
                function () {
                    return LoggerService::getInstance();
                }
            );
        }

        PacklinkPrestaShopUtility::dieJson(
            array(
                'finished' => $status->finished,
                'error' => TranslationUtility::__($status->error),
                'logs' => AutoTestLogger::getInstance()->getLogsArray(),
            )
        );
    }

    /**
     * Exports all logs as a JSON file.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    protected function exportLogs()
    {
        if (!defined('JSON_PRETTY_PRINT')) {
            define('JSON_PRETTY_PRINT', 128);
        }

        $data = json_encode(AutoTestLogger::getInstance()->getLogsArray(), JSON_PRETTY_PRINT);
        PacklinkPrestaShopUtility::dieFileFromString($data, 'auto-test-logs.json');
    }

    /**
     * Retrieves ajax action.
     *
     * @param string $controller
     * @param string $action
     * @param bool $ajax
     *
     * @return string
     *
     * @throws \PrestaShopException
     */
    private function getAction($controller, $action, $ajax = true)
    {
        return $this->context->link->getAdminLink($controller) . '&' .
            http_build_query(
                array(
                    'ajax' => $ajax,
                    'action' => $action,
                )
            );
    }
}
