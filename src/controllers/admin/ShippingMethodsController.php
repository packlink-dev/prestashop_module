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
use Packlink\BusinessLogic\Controllers\DTO\ShippingMethodConfiguration;
use Packlink\BusinessLogic\Controllers\DTO\ShippingMethodResponse;
use Packlink\BusinessLogic\Controllers\ShippingMethodController as CoreController;
use Packlink\BusinessLogic\Http\DTO\BaseDto;
use Packlink\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\BusinessLogicServices\CarrierService;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

/**
 * Class ShippingMethodsController
 */
class ShippingMethodsController extends ModuleAdminController
{
    /**
     * @var CoreController
     */
    protected $controller;

    /**
     * ShippingMethodsController constructor.
     *
     * @throws \PrestaShopException
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();
        $this->controller = new CoreController();

        $this->bootstrap = true;
    }

    /**
     * Retrieves all shipping methods.
     */
    public function displayAjaxGetAll()
    {
        $shippingMethods = $this->controller->getAll();

        PacklinkPrestaShopUtility::dieJson($this->formatCollectionJsonResponse($shippingMethods));
    }

    /**
     * Activates shipping method.
     */
    public function displayAjaxActivate()
    {
        $data = PacklinkPrestaShopUtility::getPacklinkPostData();

        if (!$data['id'] || !$this->controller->activate((int)$data['id'])) {
            PacklinkPrestaShopUtility::die400(array('message' => $this->l('Failed to select shipping method.')));
        }

        PacklinkPrestaShopUtility::dieJson(array('message' => $this->l('Shipping method successfully selected.')));
    }

    /**
     * Activates shipping method.
     */
    public function displayAjaxDeactivate()
    {
        $data = PacklinkPrestaShopUtility::getPacklinkPostData();

        if (!$data['id'] || !$this->controller->deactivate((int)$data['id'])) {
            PacklinkPrestaShopUtility::die400(
                array('message' => $this->l('Failed to deselect shipping method.'))
            );
        }

        PacklinkPrestaShopUtility::dieJson(
            array('message' => $this->l('Shipping method successfully deselected.'))
        );
    }

    /**
     * Handles saving shipping method.
     */
    public function displayAjaxSave()
    {
        $configuration = $this->getShippingMethodConfiguration();

        if (\Tools::strlen($configuration->name) > 64) {
            PacklinkPrestaShopUtility::die400(
                array(
                    'message' => $this->l('Title can have at most 64 characters.'),
                )
            );
        }

        $model = $this->controller->save($configuration);

        if ($model === false) {
            PacklinkPrestaShopUtility::die400(array('message' => $this->l('Failed to save shipping method.')));
        }

        $model->logoUrl = $this->generateCarrierLogoUrl($model->carrierName);

        if (!$model->id || !$this->controller->activate((int)$model->id)) {
            PacklinkPrestaShopUtility::die400(array('message' => $this->l('Failed to activate shipping method.')));
        }

        $model->selected = true;

        PacklinkPrestaShopUtility::dieJson($model->toArray());
    }

    /**
     * Transforms
     *
     * @param BaseDto[] $data
     *
     * @return array
     */
    protected function formatCollectionJsonResponse($data)
    {
        $collection = array();

        /** @var ShippingMethodResponse $shippingMethod */
        foreach ($data as $shippingMethod) {
            $shippingMethod->logoUrl = $this->generateCarrierLogoUrl($shippingMethod->carrierName);
            $collection[] = $shippingMethod->toArray();
        }

        return $collection;
    }

    /**
     * Retrieves shipping configuration.
     *
     * @return ShippingMethodConfiguration
     */
    protected function getShippingMethodConfiguration()
    {
        $data = PacklinkPrestaShopUtility::getPacklinkPostData();

        return ShippingMethodConfiguration::fromArray($data);
    }

    /**
     * Generates PrestaShop public URL for logo of carrier with provided title.
     *
     * @param string $carrierName Name of the carrier.
     *
     * @return string URL to carrier logo image file.
     */
    private function generateCarrierLogoUrl($carrierName)
    {
        /** @var CarrierService $carrierService */
        $carrierService = ServiceRegister::getService(ShopShippingMethodService::CLASS_NAME);

        return _PS_BASE_URL_ . __PS_BASE_URI__ . 'modules/' . $carrierService->getCarrierLogoFilePath($carrierName);
    }
}
