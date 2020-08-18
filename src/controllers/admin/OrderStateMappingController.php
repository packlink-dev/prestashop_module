<?php

use Packlink\BusinessLogic\Controllers\OrderStatusMappingController;
use Packlink\BusinessLogic\Language\Translator;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

class OrderStateMappingController extends PacklinkBaseController
{
    /** @var OrderStatusMappingController */
    private $baseController;

    public function __construct()
    {
        parent::__construct();

        $this->baseController = new OrderStatusMappingController();
    }

    /**
     * Retrieves order status mappings.
     */
    public function displayAjaxGetMappingsAndStatuses()
    {
        PacklinkPrestaShopUtility::dieJson(array(
            'systemName' => $this->getConfigService()->getIntegrationName(),
            'mappings' => $this->baseController->getMappings(),
            'packlinkStatuses' => $this->baseController->getPacklinkStatuses(),
            'orderStatuses' => $this->getSystemOrderStatuses(),
        ));
    }

    /**
     * Saves order status mappings.
     */
    public function displayAjaxSaveMappings()
    {
        $data = PacklinkPrestaShopUtility::getPacklinkPostData();
        $this->baseController->setMappings($data);

        PacklinkPrestaShopUtility::dieJson(array('success' => true));
    }

    /**
     * Retrieves all order statuses that are present in Prestashop.
     */
    private function getSystemOrderStatuses()
    {
        $result = array(
            '' => Translator::translate( 'orderStatusMapping.none' ),
        );

        $states = OrderState::getOrderStates($this->context->language->id);

        foreach ($states as $state) {
            $result[$state['id_order_state']] =  $state['name'];
        }

        return $result;
    }
}
