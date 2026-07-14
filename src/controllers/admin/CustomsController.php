<?php

use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Controllers\CustomsController as CoreController;
use Packlink\BusinessLogic\Customs\CustomsMappingService as CoreCustomsMappingService;
use Packlink\BusinessLogic\DTO\Exceptions\FrontDtoValidationException;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

class CustomsController extends PacklinkBaseController
{
    /**
     * @var CoreController
     */
    protected $controller;

    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();

        /** @var CoreCustomsMappingService $mappingService */
        $mappingService = ServiceRegister::getService(CoreCustomsMappingService::CLASS_NAME);
        $this->controller = new CoreController($mappingService);
    }

    /**
     * Returns the current customs mapping/defaults (plus the platform system name for the page text).
     */
    public function displayAjaxGetData()
    {
        $mapping = $this->controller->getData();
        $data = $mapping ? $mapping->toArray() : array();
        $data['system'] = 'PrestaShop';

        PacklinkPrestaShopUtility::dieJson($data);
    }

    /**
     * Returns the ISO country list for the country-of-origin select.
     */
    public function displayAjaxGetSupportedCountries()
    {
        PacklinkPrestaShopUtility::dieJson($this->controller->getAllCountries());
    }

    /**
     * Returns the options for the receiver tax id mapping select.
     */
    public function displayAjaxGetCustomData()
    {
        $options = array();
        foreach ($this->controller->getReceiverTaxIdOptions() as $option) {
            $options[] = $option->toArray();
        }

        PacklinkPrestaShopUtility::dieJson($options);
    }

    /**
     * Persists the submitted customs mapping/defaults; surfaces validation errors to the page.
     */
    public function displayAjaxSubmitData()
    {
        try {
            $this->controller->save(PacklinkPrestaShopUtility::getPacklinkPostData());
            PacklinkPrestaShopUtility::dieJson();
        } catch (FrontDtoValidationException $e) {
            PacklinkPrestaShopUtility::die400WithValidationErrors($e->getValidationErrors());
        }
    }
}
