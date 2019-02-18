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

use Logeecom\Infrastructure\Configuration\Configuration;
use Logeecom\Infrastructure\ServiceRegister;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

class OrderStateMappingController extends ModuleAdminController
{
    /**
     * OrderStateMappingController constructor.
     *
     * @throws \PrestaShopException
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();

        $this->bootstrap = true;
    }

    /**
     * Retrieves order status mappings.
     */
    public function displayAjaxGetMappings()
    {
        /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        $mappings = $configService->getOrderStatusMappings();

        $mappings = $mappings ?: array();

        PacklinkPrestaShopUtility::dieJson($mappings);
    }

    /**
     * Saves order status mappings.
     */
    public function displayAjaxSaveMappings()
    {
        $data = PacklinkPrestaShopUtility::getPacklinkPostData();
        /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        $configService->setOrderStatusMappings($data);

        PacklinkPrestaShopUtility::dieJson();
    }

    /**
     * Retrieves all order statuses that are present in Prestashop.
     */
    public function displayAjaxGetSystemOrderStatuses()
    {
        PacklinkPrestaShopUtility::dieJson($this->getAvailableStatuses());
    }

    /**
     * Retrieves list of available order statuses in following format:
     *
     * [
     *      [
     *          'code' => 1,
     *          'label' => Shipped,
     *      ],
     *
     *      ...
     * ]
     *
     * @return array
     */
    protected function getAvailableStatuses()
    {
        $result = array();
        $states = OrderState::getOrderStates($this->context->language->id);

        foreach ($states as $state) {
            $result[] = array('code' => $state['id_order_state'], 'label' => $state['name']);
        }

        return $result;
    }
}
