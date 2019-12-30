<?php

use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\ShipmentDraft\ShipmentDraftService;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

/**
 * Class OrderDraftController
 */
class OrderDraftController extends PacklinkBaseController
{
    /**
     * Creates order draft for order identified by ID in the request by enqueuing SendDraftTask.
     */
    public function displayAjaxCreateOrderDraft()
    {
        $data = PacklinkPrestaShopUtility::getPacklinkPostData();

        if ($data['orderId']) {
            try {
                /** @var ShipmentDraftService $shipmentDraftService */
                $shipmentDraftService = ServiceRegister::getService(ShipmentDraftService::CLASS_NAME);
                $shipmentDraftService->enqueueCreateShipmentDraftTask($data['orderId']);
            } catch (\Exception $e) {
                PacklinkPrestaShopUtility::die500(array(
                    'success' => false,
                    'message' => $e->getMessage(),
                ));
            }

            PacklinkPrestaShopUtility::dieJson(array('success' => true));
        }

        PacklinkPrestaShopUtility::die400(array('message' => 'Order ID missing'));
    }
}
