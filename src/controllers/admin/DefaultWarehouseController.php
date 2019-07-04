<?php

use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Configuration;
use Packlink\BusinessLogic\Http\DTO\Warehouse;
use Packlink\BusinessLogic\Http\Proxy;
use Packlink\BusinessLogic\Location\LocationService;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

/**
 * Class DefaultWarehouseController
 */
class DefaultWarehouseController extends ModuleAdminController
{
    /**
     * @var \Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService
     */
    protected $configService;
    /**
     * @var \Packlink\BusinessLogic\Location\LocationService
     */
    protected $locationService;
    /**
     * @var Proxy
     */
    protected $proxy;
    /**
     * @var array
     */
    protected $requiredFields;

    /**
     * DefaultWarehouseController constructor.
     * @throws \PrestaShopException
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();

        $this->configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        $this->proxy = ServiceRegister::getService(Proxy::CLASS_NAME);
        $this->locationService = ServiceRegister::getService(LocationService::CLASS_NAME);

        $this->requiredFields = array(
            'alias',
            'name',
            'surname',
            'country',
            'postal_code',
            'address',
            'phone',
            'email',
        );

        $this->bootstrap = true;
    }

    /**
     * Retrieves default warehouse data.
     */
    public function displayAjaxGetDefaultWarehouse()
    {
        $warehouse = $this->configService->getDefaultWarehouse();

        if (!$warehouse) {
            $userInfo = $this->configService->getUserInfo();
            /** @noinspection NullPointerExceptionInspection */
            $warehouse = Warehouse::fromArray(array('country' => $userInfo->country));
        }

        PacklinkPrestaShopUtility::dieJson($warehouse->toArray());
    }

    /**
     * Saves warehouse data.
     */
    public function displayAjaxSubmitDefaultWarehouse()
    {
        $data = PacklinkPrestaShopUtility::getPacklinkPostData();
        $validationResult = $this->validate($data);
        if (!empty($validationResult)) {
            PacklinkPrestaShopUtility::die400($validationResult);
        }

        $data['default'] = true;
        $warehouse = Warehouse::fromArray($data);
        $this->configService->setDefaultWarehouse($warehouse);

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

        $platformCountry = $this->configService->getUserInfo()->country;
        $result = array();
        try {
            $result = $this->locationService->searchLocations($platformCountry, $input['query']);
        } catch (\Exception $e) {
            PacklinkPrestaShopUtility::dieJson();
        }

        $arrayResult = array();
        foreach ($result as $item) {
            $arrayResult[] = $item->toArray();
        }

        PacklinkPrestaShopUtility::dieJson($arrayResult);
    }

    /**
     * Validates warehouse data.
     *
     * @param array $data
     *
     * @return array
     */
    private function validate(array $data)
    {
        $result = array();

        foreach ($this->requiredFields as $field) {
            if (empty($data[$field])) {
                $result[$field] = $this->l('Field is required.');
            }
        }

        if (!empty($data['country']) && !empty($data['postal_code'])) {
            try {
                $postalCodes = $this->proxy->getPostalCodes($data['country'], $data['postal_code']);
                if (empty($postalCodes)) {
                    $result['postal_code'] = $this->l('Postal code is not correct.');
                }
            } catch (\Exception $e) {
                $result['postal_code'] = $this->l('Postal code is not correct.');
            }
        }

        if (!empty($data['email']) && !Validate::isEmail($data['email'])) {
            $result['email'] = $this->l('Field must be valid email.');
        }

        if (!empty($data['phone'])) {
            $regex = '/^(\+|\/|\.|-|\(|\)|\d)+$/m';
            $phoneError = !preg_match($regex, $data['phone']);

            $digits = '/\d/m';
            $match = preg_match_all($digits, $data['phone']);
            $phoneError |= $match === false || $match < 3;

            if ($phoneError) {
                $result['phone'] = $this->l('Field must be valid phone number.');
            }
        }

        return $result;
    }
}
