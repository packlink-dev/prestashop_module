<?php

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
