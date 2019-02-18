<?php
/**
 * 2019 Packlink
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    Packlink <support@packlink.com>
 * @copyright 2019 Packlink Shipping S.L
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Configuration;
use Packlink\BusinessLogic\Http\DTO\Warehouse;
use Packlink\BusinessLogic\Http\Proxy;
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

        if (!empty($data['email'])) {
            if (!Validate::isEmail($data['email'])) {
                $result['email'] = $this->l('Field must be valid email.');
            }
        }

        if (!empty($data['phone'])) {
            $regex = '/^(\+|\/|\.|-|\(|\)|\d)+$/m';
            $phoneError = !preg_match($regex, $data['phone']);

            $digits = '/\d/m';
            $match = preg_match_all($digits, $data['phone']);
            $phoneError |= $match === false || $match < 3;

            if ($phoneError) {
                $result['phone'] = $this->l('Field mus be valid phone number.');
            }
        }

        return $result;
    }
}
