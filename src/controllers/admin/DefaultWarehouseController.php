<?php

use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Controllers\LocationsController;
use Packlink\BusinessLogic\Controllers\WarehouseController;
use Packlink\BusinessLogic\Country\Interfaces\CountryServiceInterface;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;
use Packlink\BusinessLogic\Warehouse\Interfaces\WarehouseServiceInterface;
use Packlink\BusinessLogic\Configuration;

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

/**
 * Class DefaultWarehouseController
 */
class DefaultWarehouseController extends PacklinkBaseController
{
    /**
     * Builds the core WarehouseController with its V2 service dependencies resolved from the registry.
     *
     * @return WarehouseController
     */
    private function getWarehouseController()
    {
        /** @var WarehouseServiceInterface $service */
        $service = ServiceRegister::getService(WarehouseServiceInterface::class);
        /** @var CountryServiceInterface $countryService */
        $countryService = ServiceRegister::getService(CountryServiceInterface::class);

        return new WarehouseController($service, $countryService);
    }

    /**
     * Retrieves default warehouse data.
     */
    public function displayAjaxGetDefaultWarehouse()
    {
        $warehouseController = $this->getWarehouseController();

        $warehouse = $warehouseController->getWarehouse();

        PacklinkPrestaShopUtility::dieJson($warehouse ? $warehouse->toArray() : array());
    }

    /**
     * Returns supported Packlink countries.
     *
     * @noinspection PhpParamsInspection
     */
    public function displayAjaxGetSupportedCountries()
    {
        $warehouseController = $this->getWarehouseController();

        Configuration::setUICountryCode($this->context->language->iso_code);
        $countries = $warehouseController->getWarehouseCountries();

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
        $warehouseController = $this->getWarehouseController();

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
