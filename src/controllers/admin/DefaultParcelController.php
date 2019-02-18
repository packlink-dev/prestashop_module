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
