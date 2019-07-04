<?php

use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Configuration;
use Packlink\BusinessLogic\Http\DTO\ParcelInfo;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

/**
 * Class DefaultParcelController
 */
class DefaultParcelController extends ModuleAdminController
{
    /**
     * @var \Packlink\BusinessLogic\Configuration
     */
    protected $configService;
    /**
     * @var array
     */
    protected $fields;
    /**
     * @var array
     */
    protected $numericFields;

    /**
     * DefaultParcelController constructor.
     *
     * @throws \PrestaShopException
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();

        $this->configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        $this->fields = array(
            'weight',
            'width',
            'height',
            'length',
        );
        $this->numericFields = array(
            'weight',
            'width',
            'height',
            'length',
        );

        $this->bootstrap = true;
    }

    /**
     * Retrieves default parcel.
     */
    public function displayAjaxGetDefaultParcel()
    {
        $parcel = $this->configService->getDefaultParcel();

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
        $this->configService->setDefaultParcel($parcelInfo);

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

        foreach ($this->fields as $field) {
            if (!empty($data[$field])) {
                if (in_array($field, $this->numericFields, true) &&
                    (!Validate::isFloat($data[$field]) || $data[$field] <= 0)
                ) {
                    $result[$field] = $this->l('Field must be valid number.');
                }
            } else {
                $result[$field] = $this->l('Field is required.');
            }
        }

        return $result;
    }
}
