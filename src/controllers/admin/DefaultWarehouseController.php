<?php

use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Country\CountryService;
use Packlink\BusinessLogic\Location\LocationService;
use Packlink\BusinessLogic\Warehouse\WarehouseService;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

/**
 * Class DefaultWarehouseController
 */
class DefaultWarehouseController extends PacklinkBaseController
{
    /**
     * Retrieves default warehouse data.
     */
    public function displayAjaxGetDefaultWarehouse()
    {
        /** @var WarehouseService $warehouseService */
        $warehouseService = ServiceRegister::getService(WarehouseService::CLASS_NAME);

        /** @var \Packlink\BusinessLogic\Warehouse\Warehouse $warehouse */
        $warehouse = $warehouseService->getWarehouse(true);

        PacklinkPrestaShopUtility::dieJson($warehouse->toArray());
    }

    /**
     * Returns supported Packlink countries.
     */
    public function displayAjaxGetSupportedCountries()
    {
        /** @var CountryService $countryService */
        $countryService = ServiceRegister::getService(CountryService::CLASS_NAME);

        $supportedCountries = $countryService->getSupportedCountries();
        foreach ($supportedCountries as $country) {
            $country->name = TranslationUtility::__($country->name);
        }

        PacklinkPrestaShopUtility::dieDtoEntities($supportedCountries);
    }

    /**
     * Saves warehouse data.
     *
     * @throws \Logeecom\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
     * @throws \Packlink\BusinessLogic\DTO\Exceptions\FrontDtoNotRegisteredException
     * @throws \Logeecom\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
     */
    public function displayAjaxSubmitDefaultWarehouse()
    {
        $data = PacklinkPrestaShopUtility::getPacklinkPostData();
        $data['default'] = true;

        /** @var WarehouseService $warehouseService */
        $warehouseService = ServiceRegister::getService(WarehouseService::CLASS_NAME);

        try {
            $warehouse = $warehouseService->updateWarehouseData($data);

            PacklinkPrestaShopUtility::dieJson($warehouse->toArray());
        } catch (\Packlink\BusinessLogic\DTO\Exceptions\FrontDtoValidationException $e) {
            PacklinkPrestaShopUtility::die400WithValidationErrors($e->getValidationErrors());
        }
    }

    /**
     * Performs location search.
     */
    public function displayAjaxSearchPostalCodes()
    {
        $input = PacklinkPrestaShopUtility::getPacklinkPostData();

        if (empty($input['query']) || empty($input['country'])) {
            PacklinkPrestaShopUtility::dieJson();
        }

        $platformCountry = $input['country'];
        $result = array();
        try {
            /** @var LocationService $locationService */
            $locationService = ServiceRegister::getService(LocationService::CLASS_NAME);
            $result = $locationService->searchLocations($platformCountry, $input['query']);
        } catch (\Exception $e) {
            PacklinkPrestaShopUtility::dieJson();
        }

        $arrayResult = array();
        foreach ($result as $item) {
            $arrayResult[] = $item->toArray();
        }

        PacklinkPrestaShopUtility::dieJson($arrayResult);
    }
}
