<?php

use Logeecom\Infrastructure\Logger\Logger;
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
        try {
            $mapping = $this->controller->getData();
        } catch (\Exception $e) {
            Logger::logWarning(
                'Failed to load customs mapping data: ' . $e->getMessage(),
                'Integration'
            );
            PacklinkPrestaShopUtility::die500();

            return;
        }

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
     * Returns the data-mapping field definitions for the customs settings page (one per mappable
     * customs field, each with its selectable PrestaShop sources).
     */
    public function displayAjaxGetCustomData()
    {
        $fields = array();
        foreach ($this->controller->getMappingFieldsOptions() as $fieldOptions) {
            $fields[] = $fieldOptions->toArray();
        }

        PacklinkPrestaShopUtility::dieJson($fields);
    }

    /**
     * Persists the submitted customs mapping/defaults; surfaces validation errors to the page.
     */
    public function displayAjaxSubmitData()
    {
        $data = PacklinkPrestaShopUtility::getPacklinkPostData();
        if (!is_array($data)) {
            // getPacklinkPostData() returns null on absent/invalid JSON; core save() type-hints
            // array and would throw an uncatchable TypeError, so reject the request cleanly.
            PacklinkPrestaShopUtility::die400();

            return;
        }

        try {
            $this->controller->save($data);
            PacklinkPrestaShopUtility::dieJson();
        } catch (FrontDtoValidationException $e) {
            PacklinkPrestaShopUtility::die400WithValidationErrors($e->getValidationErrors());
        }
    }
}
