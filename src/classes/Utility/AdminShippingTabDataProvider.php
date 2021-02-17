<?php

namespace Packlink\PrestaShop\Classes\Utility;

use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\TaskExecution\QueueItem;
use Logeecom\Infrastructure\Utility\TimeProvider;
use Packlink\BusinessLogic\Configuration;
use Packlink\BusinessLogic\Language\Translator;
use Packlink\BusinessLogic\Order\OrderService;
use Packlink\BusinessLogic\OrderShipmentDetails\Models\OrderShipmentDetails;
use Packlink\BusinessLogic\OrderShipmentDetails\OrderShipmentDetailsService;
use Packlink\BusinessLogic\ShipmentDraft\Objects\ShipmentDraftStatus;
use Packlink\BusinessLogic\ShipmentDraft\ShipmentDraftService;
use Packlink\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Packlink\BusinessLogic\ShippingMethod\ShippingMethodService;
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
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public static function prepareShippingTabData(\Context $context, \Module $module, $orderId)
    {
        self::$context = $context;
        self::$module = $module;

        Configuration::setCurrentLanguage($context->language->iso_code);

        /* @var OrderShipmentDetailsService $shipmentDetailsService */
        $shipmentDetailsService = ServiceRegister::getService(OrderShipmentDetailsService::CLASS_NAME);

        $shipmentDetails = $shipmentDetailsService->getDetailsByOrderId($orderId);

        self::prepareDraftButtonSection($orderId, $shipmentDetails);
        if ($shipmentDetails !== null) {
            self::prepareLabelsTemplate($shipmentDetails);
        }

        self::$context->smarty->assign(self::getLinks($orderId));

        self::$context->controller->addJS(
            array(
                self::$module->getPathUri() . 'views/js/PrestaCreateOrderDraft.js?v=' . self::$module->version,
                self::$module->getPathUri() . 'views/js/PrestaAjaxService.js?v=' . self::$module->version,
                self::$module->getPathUri() . 'views/js/core/AjaxService.js?v=' . self::$module->version,
                self::$module->getPathUri() . 'views/js/core/ResponseService.js?v=' . self::$module->version,
                self::$module->getPathUri() . 'views/js/core/StateUUIDService.js?v=' . self::$module->version,
            ),
            false
        );
    }

    /**
     * Returns shipping content data for the order with provided ID.
     *
     * @param \Context $context
     * @param \Module $module
     * @param int $orderId
     *
     * @return array
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public static function getShippingContentData(\Context $context, \Module $module, $orderId)
    {
        Configuration::setCurrentLanguage($context->language->iso_code);

        self::$context = $context;
        self::$module = $module;

        /* @var OrderShipmentDetailsService $shipmentDetailsService */
        $shipmentDetailsService = ServiceRegister::getService(OrderShipmentDetailsService::CLASS_NAME);
        $shipmentDetails = $shipmentDetailsService->getDetailsByOrderId($orderId);

        return array_merge(
            self::getLinks($orderId),
            self::getDraftParams($orderId, $shipmentDetails),
            self::getShippingDetails($orderId, $shipmentDetails),
            $shipmentDetails ? self::getLabelParams($shipmentDetails) : array()
        );
    }

    /**
     * Prepares a section with a button for creating a shipment draft.
     *
     * @param int $orderId ID of the order.
     * @param OrderShipmentDetails|null $shipmentDetails Shipping details for an order.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private static function prepareDraftButtonSection($orderId, $shipmentDetails = null)
    {
        self::$context->smarty->assign(self::getDraftParams($orderId, $shipmentDetails));
    }

    /**
     * Returns shipping details for Packlink shipping tab.
     *
     * @param string $orderId ID of the order.
     * @param OrderShipmentDetails $shipmentDetails Shipment details for an order.
     *
     * @return array
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private static function getShippingDetails($orderId, OrderShipmentDetails $shipmentDetails = null)
    {
        if ($shipmentDetails === null) {
            return array(
                'reference' => ''
            );
        }

        /** @var TimeProvider $timeProvider */
        $timeProvider = ServiceRegister::getService(TimeProvider::CLASS_NAME);

        $order = new \Order((int)$orderId);

        /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\CarrierService $carrierService */
        $carrierService = ServiceRegister::getService(ShopShippingMethodService::CLASS_NAME);
        /** @var ShippingMethodService $shippingMethodService */
        $shippingMethodService = ServiceRegister::getService(ShippingMethodService::CLASS_NAME);

        $carrier = new \Carrier($order->id_carrier);

        $shippingMethodId = $carrierService->getShippingMethodId((int)$carrier->id_reference);
        $shippingMethod = null;

        if ($shippingMethodId) {
            $shippingMethod = $shippingMethodService->getShippingMethod($shippingMethodId);
        }

        return array(
            'name' => $shippingMethod ? $shippingMethod->getTitle() : '',
            'reference' => $shipmentDetails->getReference(),
            'deleted' => $shipmentDetails->isDeleted(),
            'icon' => $shippingMethod ? $shippingMethod->getLogoUrl() : '',
            'status' => $shipmentDetails->getShippingStatus() ?: '',
            'time' => $shipmentDetails->getLastStatusUpdateTime() !== null
                ? $timeProvider->serializeDate($shipmentDetails->getLastStatusUpdateTime(), 'd.m.Y H:i:s')
                : '',
            'carrier_tracking_numbers' => $shipmentDetails->getCarrierTrackingNumbers(),
            'carrier_tracking_url' => $shipmentDetails->getCarrierTrackingUrl() ?: '',
            'packlink_shipping_price' => $shipmentDetails->getShippingCost() !== null
                ? $shipmentDetails->getShippingCost() . ' €' : '',
            'link' => $shipmentDetails->getShipmentUrl(),
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

        self::$context->smarty->assign(self::getLabelParams($shipmentDetails));
    }

    /**
     * Returns links for shipment details page.
     *
     * @param int $orderId
     *
     * @return array
     *
     * @throws \PrestaShopException
     */
    private static function getLinks($orderId)
    {
        $printLabelsUrl = self::$context->link->getAdminLink('BulkShipmentLabels');
        if (strpos($printLabelsUrl, _PS_BASE_URL_) === false) {
            $admin = explode(DIRECTORY_SEPARATOR,_PS_ADMIN_DIR_);
            $adminArray = array_slice($admin, -1);
            $adminFolder = array_pop($adminArray);
            $printLabelsUrl = _PS_BASE_URL_ . __PS_BASE_URI__ . $adminFolder . '/' . $printLabelsUrl;
        }

        return array(
            'printLabelsUrl' => $printLabelsUrl,
            'pluginBasePath' => self::$module->getPathUri(),
            'orderId' => $orderId,
            'createDraftUrl' => self::$context->link->getAdminLink('OrderDraft') . '&' .
                http_build_query(
                    array(
                        'ajax' => true,
                        'action' => 'createOrderDraft',
                    )
                ),
        );
    }

    /**
     * Returns order shipment draft parameters.
     *
     * @param int $orderId
     * @param \Packlink\BusinessLogic\OrderShipmentDetails\Models\OrderShipmentDetails $shipmentDetails
     *
     * @return array
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private static function getDraftParams($orderId, $shipmentDetails = null)
    {
        /** @var ShipmentDraftService $shipmentDraftService */
        $shipmentDraftService = ServiceRegister::getService(ShipmentDraftService::CLASS_NAME);

        $draftStatus = $shipmentDraftService->getDraftStatus($orderId);
        $displayDraftButton = true;

        switch ($draftStatus->status) {
            case ShipmentDraftStatus::NOT_QUEUED:
                $message = Translator::translate('orderListAndDetails.createOrderDraft');
                break;
            case QueueItem::FAILED:
                $message = Translator::translate(
                    'orderListAndDetails.draftCreateFailed',
                    array($draftStatus->message)
                );
                break;
            default:
                $message = Translator::translate('orderListAndDetails.draftIsBeingCreated');
                $displayDraftButton = false;
                break;
        }

        return array(
            'shipping' => !$displayDraftButton ? (object)self::getShippingDetails($orderId, $shipmentDetails) : '',
            'message' => $message,
            'displayDraftButton' => $displayDraftButton,
        );
    }

    /**
     * Returns order shipment label parameters.
     *
     * @param \Packlink\BusinessLogic\OrderShipmentDetails\Models\OrderShipmentDetails $shipmentDetails
     *
     * @return array
     */
    private static function getLabelParams(OrderShipmentDetails $shipmentDetails)
    {
        /** @var OrderService $orderService */
        $orderService = ServiceRegister::getService(OrderService::CLASS_NAME);

        $labels = $shipmentDetails->getShipmentLabels();
        $status = $shipmentDetails->getStatus() ?: ShipmentStatus::STATUS_PENDING;

        $isLabelPrinted = !empty($labels) && $labels[0]->isPrinted();

        return array(
            'orderId' => $shipmentDetails->getOrderId(),
            'isLabelPrinted' => $isLabelPrinted,
            'date' => !empty($labels) ? $labels[0]->getDateCreated()->format('d/m/Y') : '',
            'status' => Translator::translate(
                $isLabelPrinted ? 'orderListAndDetails.printed' : 'orderListAndDetails.ready'
            ),
            'isLabelAvailable' => !empty($labels) || $orderService->isReadyToFetchShipmentLabels($status),
            'number' => '#PLSL1',
        );
    }
}
