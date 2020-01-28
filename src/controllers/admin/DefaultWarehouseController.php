<?php

use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Location\LocationService;
use Packlink\BusinessLogic\Warehouse\WarehouseService;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

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

        $warehouse = $warehouseService->getWarehouse(true);

        PacklinkPrestaShopUtility::dieJson($warehouse->toArray());
    }

    /**
     * Saves warehouse data.
     *
     * @throws \Packlink\BusinessLogic\DTO\Exceptions\FrontDtoNotRegisteredException
     */
    public function displayAjaxSubmitDefaultWarehouse()
    {
        $data = PacklinkPrestaShopUtility::getPacklinkPostData();
        $data['default'] = true;

        /** @var WarehouseService $warehouseService */
        $warehouseService = ServiceRegister::getService(WarehouseService::CLASS_NAME);

        try {
            $warehouseService->setWarehouse($data);
        } catch (\Packlink\BusinessLogic\DTO\Exceptions\FrontDtoValidationException $e) {
            PacklinkPrestaShopUtility::die400WithValidationErrors($e->getValidationErrors());
        }

        PacklinkPrestaShopUtility::dieJson($data);
    }

    /**
     * Performs location search.
     */
    public function displayAjaxSearchPostalCodes()
    {
        $input = PacklinkPrestaShopUtility::getPacklinkPostData();

        if (empty($input['query'])) {
            PacklinkPrestaShopUtility::dieJson();
        }

        $platformCountry = $this->getConfigService()->getUserInfo()->country;
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
