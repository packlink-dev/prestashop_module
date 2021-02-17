<?php

namespace Packlink\PrestaShop\Classes\Overrides;

use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\TaskExecution\QueueItem;
use Packlink\BusinessLogic\Order\OrderService;
use Packlink\BusinessLogic\OrderShipmentDetails\OrderShipmentDetailsService;
use Packlink\BusinessLogic\ShipmentDraft\ShipmentDraftService;
use Packlink\BusinessLogic\ShippingMethod\Utility\ShipmentStatus;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\Repositories\OrderRepository;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

/**
 * Class AdminOrdersController.
 *
 * @package Packlink\PrestaShop\Classes\Overrides
 */
class AdminOrdersController
{
    const PACKLINK_ORDER_DRAFT_TEMPLATE = 'packlink/views/templates/admin/packlink_order_draft/order_draft.tpl';
    const PACKLINK_ORDER_ICONS_TEMPLATE = 'packlink/views/templates/admin/packlink_order_icons/_print_pdf_icon.tpl';

    /**
     * AdminOrdersController constructor.
     */
    public function __construct()
    {
        Bootstrap::init();
    }

    /**
     * Inserts the Packlink order grid column.
     *
     * @param array $fields_list
     *
     * @return array
     */
    public function insertOrderColumn(array $fields_list)
    {
        $packlinkElement = array(
            'title' => TranslationUtility::__('Packlink PRO Shipping'),
            'align' => 'text-center',
            'filter_key' => 'a!id_order',
            'callback' => 'getOrderDraft',
            'remove_onclick' => true,
        );

        return $this->insertElementIntoArrayAfterSpecificKey(
            $fields_list,
            'payment',
            array(OrderRepository::PACKLINK_ORDER_DRAFT_FIELD => $packlinkElement)
        );
    }

    /**
     * Adds bulk action for printing labels to the array of bulk actions.
     *
     * @param array $bulk_actions
     */
    public function addBulkActions(array &$bulk_actions)
    {
        $bulk_actions['printShipmentLabels'] = array(
            'text' => TranslationUtility::__('Print Shipment Labels'),
            'icon' => 'icon-tag',
        );
    }

    /**
     * Renders icons for printing PDF files.
     *
     * @param string $orderId
     * @param \Context $context
     *
     * @return mixed
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     * @throws \SmartyException
     */
    public function renderPdfIcons($orderId, \Context $context)
    {
        $order = new \Order((int)$orderId);
        if (!$this->validateOrder($order)) {
            return '';
        }

        $printLabelsUrl = $context->link->getAdminLink('BulkShipmentLabels');
        if (strpos($printLabelsUrl, _PS_BASE_URL_) === false) {
            $admin = explode(DIRECTORY_SEPARATOR,_PS_ADMIN_DIR_);
            $adminArray = array_slice($admin, -1);
            $adminFolder = array_pop($adminArray);
            $printLabelsUrl = _PS_BASE_URL_ . __PS_BASE_URI__ . $adminFolder . '/' . $printLabelsUrl;
        }

        /** @var OrderShipmentDetailsService $shipmentDetailsService */
        $shipmentDetailsService = ServiceRegister::getService(OrderShipmentDetailsService::CLASS_NAME);
        $shipmentDetails = $shipmentDetailsService->getDetailsByOrderId((string)$orderId);
        $shipmentLabels = $shipmentDetails ? $shipmentDetails->getShipmentLabels() : array();
        $status = $shipmentDetails ? $shipmentDetails->getStatus() : ShipmentStatus::STATUS_PENDING;
        /** @var OrderService $orderService */
        $orderService = ServiceRegister::getService(OrderService::CLASS_NAME);

        $context->smarty->assign(array(
            'orderId' => $orderId,
            'order' => $order,
            'isLabelAvailable' => $orderService->isReadyToFetchShipmentLabels($status),
            'isLabelPrinted' => !empty($shipmentLabels) && $shipmentLabels[0]->isPrinted(),
            'printLabelsUrl' => $printLabelsUrl,
        ));

        $context->controller->addJS(
            array(
                _PS_MODULE_DIR_ . 'packlink/views/js/PrestaPrintShipmentLabels.js',
                _PS_MODULE_DIR_ . 'packlink/views/js/PrestaAjaxService.js',
                _PS_MODULE_DIR_ . 'packlink/views/js/OrderOverviewDraft.js',
                _PS_MODULE_DIR_ . 'packlink/views/js/core/AjaxService.js',
                _PS_MODULE_DIR_ . 'packlink/views/js/core/ResponseService.js',
                _PS_MODULE_DIR_ . 'packlink/views/js/core/StateUUIDService.js',
            )
        );

        $context->controller->addCSS(
            array(
                _PS_MODULE_DIR_ . 'packlink/views/css/packlink-order-overview.css',
            )
        );

        return $context->smarty->createTemplate(
            _PS_MODULE_DIR_ . self::PACKLINK_ORDER_ICONS_TEMPLATE,
            $context->smarty
        )->fetch();
    }

    /**
     * Returns template that should be rendered in order draft column within orders table.
     *
     * @param string $orderId Order Id.
     * @param \Context $context
     *
     * @return string Rendered template output.
     *
     * @throws \SmartyException
     * @throws \PrestaShopException
     */
    public function getOrderDraft($orderId, \Context $context)
    {
        if ($orderId === '') {
            return '';
        }

        /** @var OrderShipmentDetailsService $shipmentDetailsService */
        $shipmentDetailsService = ServiceRegister::getService(OrderShipmentDetailsService::CLASS_NAME);
        /** @var ShipmentDraftService $draftService */
        $draftService = ServiceRegister::getService(ShipmentDraftService::CLASS_NAME);

        $shipmentDetails = $shipmentDetailsService->getDetailsByOrderId((string)$orderId);
        $draftStatus = $draftService->getDraftStatus((string)$orderId);
        $status = $draftStatus->status === QueueItem::IN_PROGRESS ? QueueItem::QUEUED : $draftStatus->status;
        $draftCreated = $status === QueueItem::COMPLETED && $shipmentDetails;

        $context->smarty->assign(
            array(
                'orderId' => $orderId,
                'imgSrc' => _PS_BASE_URL_ . _MODULE_DIR_ . 'packlink/logo.png',
                'draftStatus' => $status,
                'deleted' => $draftCreated ? $shipmentDetails->isDeleted() : false,
                'orderDraftLink' => $draftCreated ? $shipmentDetails->getShipmentUrl() : '#',
                'draftStatusUrl' => $this->getAjaxControllerUrl($context, 'OrderDraft', 'getDraftStatus'),
                'createDraftUrl' => $this->getAjaxControllerUrl($context, 'OrderDraft', 'createOrderDraft'),
            )
        );

        return $context->smarty->createTemplate(
            _PS_MODULE_DIR_ . self::PACKLINK_ORDER_DRAFT_TEMPLATE,
            $context->smarty
        )->fetch();
    }

    /**
     * Returns URL endpoint of ajax controller action.
     *
     * @param \Context $context
     * @param string $controller
     * @param string $action
     *
     * @return string
     *
     * @throws \PrestaShopException
     */
    private function getAjaxControllerUrl(\Context $context, $controller, $action)
    {
        return $context->link->getAdminLink($controller) . '&' .
            http_build_query(
                array(
                    'ajax' => true,
                    'action' => $action,
                )
            );
    }

    /**
     * Validates provided order.
     *
     * @param \Order $order
     *
     * @return bool Returns true if order object is valid, otherwise returns false.
     */
    private function validateOrder(\Order $order)
    {
        static $valid_order_state = array();

        if (!\Validate::isLoadedObject($order)) {
            return false;
        }

        if (!isset($valid_order_state[$order->current_state])) {
            $valid_order_state[$order->current_state] = \Validate::isLoadedObject($order->getCurrentOrderState());
        }

        if (!$valid_order_state[$order->current_state]) {
            return false;
        }

        return true;
    }

    /**
     * Insert a value or key/value pair after a specific key in an array.
     * If key doesn't exist, value is appended to the end of the array.
     *
     * @param array $array Array in which the value should be inserted.
     * @param string $key Key of the element that should precede inserted element.
     * @param array $new New element that is being inserted into array.
     *
     * @return array Array with new element inserted at a specified position.
     */
    private function insertElementIntoArrayAfterSpecificKey(array $array, $key, array $new)
    {
        $keys = array_keys($array);
        $index = array_search($key, $keys, true);
        $pos = false === $index ? count($array) : $index + 1;

        return array_merge(array_slice($array, 0, $pos), $new, array_slice($array, $pos));
    }
}
