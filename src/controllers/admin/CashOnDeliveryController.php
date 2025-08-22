<?php

use Packlink\BusinessLogic\Http\DTO\CashOnDelivery;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\BusinessLogicServices\OfflinePaymentService;
use Packlink\BusinessLogic\Controllers\CashOnDeliveryController as CoreController;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;


/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';


class CashOnDeliveryController extends PacklinkBaseController
{
    /**
     * @var OfflinePaymentService
     */
    protected $offlinePaymentService;

    /**
     * @var CoreController $controller
     */
    protected $controller;

    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();

        $this->offlinePaymentService = new OfflinePaymentService();
        $this->controller = new CoreController();
    }

    public function displayAjaxGetData()
    {
        $configuration = $this->getAccountConfiguration();
        $configArray = array();

        if ($configuration !== null) {
            $configArray = $configuration->toArray();
        }

        PacklinkPrestaShopUtility::dieJson(array(
            'paymentMethods' => $this->getOfflinePayments(),
            'configuration' => $configArray,
        ));
    }


    /**
     * @throws \Packlink\BusinessLogic\DTO\Exceptions\FrontDtoValidationException
     */
    public function displayAjaxSaveData()
    {
        $rawData = PacklinkPrestaShopUtility::getPacklinkPostData();

        $this->controller->saveConfig($rawData);
    }

    private function getOfflinePayments()
    {
        return $this->offlinePaymentService->getOfflinePayments();
    }


    /**
     * Retrieves Packlink account configuration and checks if an account exists.
     *
     * @return CashOnDelivery|null
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    private function getAccountConfiguration()
    {
        return $this->controller->getCashOnDeliveryConfiguration();
    }
}