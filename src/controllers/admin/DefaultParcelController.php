<?php

use Packlink\BusinessLogic\Http\DTO\ParcelInfo;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

/**
 * Class DefaultParcelController
 */
class DefaultParcelController extends PacklinkBaseController
{
    /**
     * Retrieves default parcel.
     */
    public function displayAjaxGetDefaultParcel()
    {
        $parcel = $this->getConfigService()->getDefaultParcel();

        if (!$parcel) {
            PacklinkPrestaShopUtility::dieJson();
        }

        PacklinkPrestaShopUtility::dieJson($parcel->toArray());
    }

    /**
     * Saves default parcel.
     */
    public function displayAjaxSubmitDefaultParcel()
    {
        $data = PacklinkPrestaShopUtility::getPacklinkPostData();
        $validationResult = $this->validate($data);
        if (!empty($validationResult)) {
            PacklinkPrestaShopUtility::die400($validationResult);
        }

        $data['default'] = true;

        $parcelInfo = ParcelInfo::fromArray($data);
        $this->getConfigService()->setDefaultParcel($parcelInfo);

        PacklinkPrestaShopUtility::dieJson($data);
    }

    /**
     * Validates default parcel data.
     *
     * @param array $data
     *
     * @return array
     */
    private function validate(array $data)
    {
        $result = array();
        $fields = array('weight', 'width', 'height', 'length');
        foreach ($fields as $field) {
            if (!empty($data[$field])) {
                /** @noinspection NotOptimalIfConditionsInspection */
                if (!Validate::isFloat($data[$field]) || $data[$field] <= 0) {
                    $result[$field] = $this->l('Field must be valid number.');
                }
            } else {
                $result[$field] = $this->l('Field is required.');
            }
        }

        return $result;
    }
}
