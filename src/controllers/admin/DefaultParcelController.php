<?php

use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;
use Packlink\BusinessLogic\Controllers\DefaultParcelController as BaseParcelController;

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

/**
 * Class DefaultParcelController
 */
class DefaultParcelController extends PacklinkBaseController
{
    /** @var BaseParcelController */
    private $parcelController;

    public function __construct()
    {
        parent::__construct();

        $this->parcelController = new BaseParcelController();
    }

    /**
     * Retrieves default parcel.
     */
    public function displayAjaxGetDefaultParcel()
    {
        $parcel = $this->parcelController->getDefaultParcel();

        PacklinkPrestaShopUtility::dieJson($parcel ? $parcel->toArray() : array());
    }

    /**
     * Saves default parcel.
     *
     * @throws \Logeecom\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
     */
    public function displayAjaxSubmitDefaultParcel()
    {
        $data = PacklinkPrestaShopUtility::getPacklinkPostData();
        $data['default'] = true;

        try {
            $this->parcelController->setDefaultParcel($data);

            $this->displayAjaxGetDefaultParcel();
        } catch (\Packlink\BusinessLogic\DTO\Exceptions\FrontDtoValidationException $e) {
            PacklinkPrestaShopUtility::die400WithValidationErrors($e->getValidationErrors());
        }
    }
}
