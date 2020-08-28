<?php

use Packlink\BusinessLogic\Controllers\LocationsController;
use Packlink\BusinessLogic\Controllers\WarehouseController;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;
use Packlink\BusinessLogic\Controllers\RegistrationRegionsController as CountryController;

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
        $warehouseController = new WarehouseController();

        $warehouse = $warehouseController->getWarehouse();

        PacklinkPrestaShopUtility::dieJson($warehouse ? $warehouse->toArray() : array());
    }

    /**
     * Returns supported Packlink countries.
     */
    public function displayAjaxGetSupportedCountries()
    {
        $countryController = new CountryController();

        $countries = $countryController->getRegions();

        PacklinkPrestaShopUtility::dieDtoEntities($countries);
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
        $warehouseController = new WarehouseController();

        try {
            $warehouse = $warehouseController->updateWarehouse($data);

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

        $locationsController = new LocationsController();

        try {
            PacklinkPrestaShopUtility::dieDtoEntities($locationsController->searchLocations($input));
        } catch (\Exception $e) {
            PacklinkPrestaShopUtility::dieJson();
        }
    }
}
