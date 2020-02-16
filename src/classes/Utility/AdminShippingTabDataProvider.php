<?php

namespace Packlink\PrestaShop\Classes\Utility;

use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\TaskExecution\QueueItem;
use Logeecom\Infrastructure\Utility\TimeProvider;
use Packlink\BusinessLogic\Configuration;
use Packlink\BusinessLogic\Order\OrderService;
use Packlink\BusinessLogic\OrderShipmentDetails\Models\OrderShipmentDetails;
use Packlink\BusinessLogic\OrderShipmentDetails\OrderShipmentDetailsService;
use Packlink\BusinessLogic\ShipmentDraft\Objects\ShipmentDraftStatus;
use Packlink\BusinessLogic\ShipmentDraft\ShipmentDraftService;
use Packlink\BusinessLogic\ShippingMethod\Utility\ShipmentStatus;

/**
 * Class AdminShippingTabDataProvider.
 *
 * @package Packlink\PrestaShop\Classes\Utility
 */
class AdminShippingTabDataProvider
{
    /**
     * @var \Context
     */
    private static $context;
    /**
     * @var \Module
     */
    private static $module;

    /**
     * Prepares Packlink shipping tab data based on the state of order details and draft task.
     *
     * @param \Context $context
     * @param \Module $module
     * @param string $orderId ID of the order.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public static function prepareShippingTabData(\Context $context, \Module $module, $orderId)
    {
        self::$context = $context;
        self::$module = $module;

        /* @var OrderShipmentDetailsService $shipmentDetailsService */
        $shipmentDetailsService = ServiceRegister::getService(OrderShipmentDetailsService::CLASS_NAME);

        $shipmentDetails = $shipmentDetailsService->getDetailsByOrderId($orderId);

        self::prepareDraftButtonSection($orderId, $shipmentDetails);
        if ($shipmentDetails !== null) {
            self::prepareLabelsTemplate($shipmentDetails);
        }

        self::$context->smarty->assign(array(
            'printLabelsUrl' => self::$context->link->getAdminLink('BulkShipmentLabels'),
            'pluginBasePath' => self::$module->getPathUri(),
            'orderId' => $orderId,
            'createDraftUrl' => self::$context->link->getAdminLink('OrderDraft') . '&' .
                http_build_query(
                    array(
                        'ajax' => true,
                        'action' => 'createOrderDraft',
                    )
                ),
        ));

        self::$context->controller->addJS(
            array(
                self::$module->getPathUri() . 'views/js/PrestaCreateOrderDraft.js?v=' . self::$module->version,
                self::$module->getPathUri() . 'views/js/core/AjaxService.js?v=' . self::$module->version,
                self::$module->getPathUri() . 'views/js/PrestaAjaxService.js?v=' . self::$module->version,
            ),
            false
        );
    }

    /**
     * Prepares a section with a button for creating a shipment draft.
     *
     * @param int $orderId ID of the order.
     * @param OrderShipmentDetails|null $shipmentDetails Shipping details for an order.
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private static function prepareDraftButtonSection($orderId, $shipmentDetails = null)
    {
        /** @var ShipmentDraftService $shipmentDraftService */
        $shipmentDraftService = ServiceRegister::getService(ShipmentDraftService::CLASS_NAME);

        $draftStatus = $shipmentDraftService->getDraftStatus($orderId);
        $displayDraftButton = true;

        switch ($draftStatus->status) {
            case ShipmentDraftStatus::NOT_QUEUED:
                $message = TranslationUtility::__('Create order draft in Packlink PRO');
                break;
            case QueueItem::FAILED:
                $message = TranslationUtility::__(
                    'Previous attempt to create a draft failed. Error: %s',
                    array($draftStatus->message)
                );
                break;
            default:
                $message = TranslationUtility::__('Draft is currently being created in Packlink');
                $displayDraftButton = false;
                break;
        }

        self::$context->smarty->assign(array(
            'shipping' => !$displayDraftButton ? (object)self::prepareShippingObject($orderId, $shipmentDetails) : '',
            'message' => $message,
            'displayDraftButton' => $displayDraftButton,
        ));
    }

    /**
     * Prepares shipping details object for Packlink shipping tab.
     *
     * @param string $orderId ID of the order.
     * @param OrderShipmentDetails $shipmentDetails Shipment details for an order.
     *
     * @return array
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private static function prepareShippingObject($orderId, OrderShipmentDetails $shipmentDetails = null)
    {
        if ($shipmentDetails === null) {
            return array();
        }

        /** @var TimeProvider $timeProvider */
        $timeProvider = ServiceRegister::getService(TimeProvider::CLASS_NAME);

        $order = new \Order((int)$orderId);
        $carrier = new \Carrier((int)$order->id_carrier);

        return array(
            'name' => $carrier->name,
            'reference' => $shipmentDetails->getReference(),
            'deleted' => $shipmentDetails->isDeleted(),
            'icon' => file_exists(_PS_SHIP_IMG_DIR_ . '/' . (int)$carrier->id . '.jpg') ?
                __PS_BASE_URI__ . 'img/s/' . (int)$carrier->id . '.jpg' : '',
            'status' => $shipmentDetails->getShippingStatus() ?: '',
            'time' => $shipmentDetails->getLastStatusUpdateTime() !== null
                ? $timeProvider->serializeDate($shipmentDetails->getLastStatusUpdateTime(), 'd.m.Y H:i:s')
                : '',
            'carrier_tracking_numbers' => $shipmentDetails->getCarrierTrackingNumbers(),
            'carrier_tracking_url' => $shipmentDetails->getCarrierTrackingUrl() ?: '',
            'packlink_shipping_price' => $shipmentDetails->getShippingCost() !== null
                ? $shipmentDetails->getShippingCost() . ' €' : '',
            'link' => self::getOrderDraftUrl($shipmentDetails->getReference()),
        );
    }

    /**
     * Prepares a template for displaying and printing labels.
     *
     * @param OrderShipmentDetails $shipmentDetails Shipment details for an order.
     */
    private static function prepareLabelsTemplate(OrderShipmentDetails $shipmentDetails)
    {
        self::$context->controller->addJS(
            self::$module->getPathUri() . 'views/js/PrestaPrintShipmentLabels.js?v=' . self::$module->version,
            false
        );

        /** @var OrderService $orderService */
        $orderService = ServiceRegister::getService(OrderService::CLASS_NAME);

        $labels = $shipmentDetails->getShipmentLabels();
        $status = $shipmentDetails->getStatus() ?: ShipmentStatus::STATUS_PENDING;

        $isLabelPrinted = !empty($labels) && $labels[0]->isPrinted();

        self::$context->smarty->assign(
            array(
                'orderId' => $shipmentDetails->getOrderId(),
                'isLabelPrinted' => $isLabelPrinted,
                'date' => !empty($labels) ? $labels[0]->getDateCreated()->format('d/m/Y') : '',
                'status' => TranslationUtility::__(
                    $isLabelPrinted ? 'Printed' : 'Ready'
                ),
                'isLabelAvailable' => !empty($labels) || $orderService->isReadyToFetchShipmentLabels($status),
                'number' => '#PLSL1',
            )
        );
    }

    /**
     * Returns link to order draft on Packlink for the provided shipment reference.
     *
     * @param string $reference Shipment reference.
     *
     * @return string Link to order draft on Packlink.
     */
    private static function getOrderDraftUrl($reference)
    {
        /** @var Configuration $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        $userInfo = $configService->getUserInfo();

        $userDomain = 'com';
        if ($userInfo !== null && in_array($userInfo->country, array('ES', 'DE', 'IT', 'FR'))) {
            $userDomain = strtolower($userInfo->country);
        }

        return "https://pro.packlink.$userDomain/private/shipments/$reference";
    }
}
