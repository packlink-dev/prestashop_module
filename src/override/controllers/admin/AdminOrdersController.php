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

/**
 * Class AdminOrdersController
 */
class AdminOrdersController extends AdminOrdersControllerCore
{
    const PACKLINK_ORDER_DRAFT_TEMPLATE = 'packlink/views/templates/admin/packlink_order_draft/order_draft.tpl';
    const PACKLINK_ORDER_ICONS_TEMPLATE = 'packlink/views/templates/admin/packlink_order_icons/_print_pdf_icon.tpl';

    /**
     * AdminOrdersController constructor.
     */
    public function __construct()
    {
        parent::__construct();

        require_once _PS_MODULE_DIR_ . '/packlink/vendor/autoload.php';
        $column = Packlink\PrestaShop\Classes\Repositories\OrderRepository::PACKLINK_ORDER_DRAFT_FIELD;
        $this->_select .= ',a.' . $column . ' AS ' . $column;

        $packlinkElement = array(
            'title' => $this->l('Packlink PRO Shipping'),
            'align' => 'text-center',
            'filter_key' => 'a!' . $column,
            'callback' => 'getOrderDraft',
        );

        $this->fields_list = $this->insertElementIntoArrayAfterSpecificKey(
            $this->fields_list,
            'payment',
            array($column => $packlinkElement)
        );

        $this->bulk_actions = array_merge(
            $this->bulk_actions,
            array(
                'printShipmentLabels' => array('text' => $this->l('Print Shipment Labels'), 'icon' => 'icon-tag'),
            )
        );
    }

    /**
     * Renders invoice and shipment label icons.
     *
     * @param int $orderId ID of the order.
     * @param array $tr Table row.
     *
     * @return string Rendered template output.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function printPDFIcons($orderId, $tr)
    {
        $order = new \Order($orderId);
        if (!$this->validateOrder($order)) {
            return '';
        }

        Packlink\PrestaShop\Classes\Bootstrap::init();

        /** @var \Packlink\PrestaShop\Classes\Repositories\OrderRepository $orderRepository */
        $orderRepository = Logeecom\Infrastructure\ServiceRegister::getService(
            Packlink\PrestaShop\Classes\Repositories\OrderRepository::CLASS_NAME
        );
        $shipmentLabels = $orderRepository->getLabelsByOrderId((int)$orderId);

        $labels = array();
        /** @var \Packlink\PrestaShop\Classes\Objects\ShipmentLabel $shipmentLabel */
        foreach ($shipmentLabels as $shipmentLabel) {
            $labels[] = (object)array(
                'printed' => $shipmentLabel->isPrinted(),
                'link' => $shipmentLabel->getLink(),
            );
        }

        $printLabelUrl = $this->context->link->getAdminLink('ShipmentLabels') . '&' .
            http_build_query(
                array(
                    'ajax' => true,
                    'action' => 'setLabelPrinted',
                )
            );

        $printLabelsUrl = $this->context->link->getAdminLink('BulkShipmentLabels');

        $this->context->smarty->assign(array(
            'orderId' => $orderId,
            'order' => $order,
            'labels' => $labels,
            'printLabelUrl' => $printLabelUrl,
            'printLabelsUrl' => $printLabelsUrl,
        ));

        $this->context->controller->addJS(_PS_MODULE_DIR_ . 'packlink/views/js/PrestaPrintShipmentLabels.js');
        $this->context->controller->addJS(_PS_MODULE_DIR_ . 'packlink/views/js/PrestaAjaxService.js');

        return $this->context->smarty->createTemplate(
            _PS_MODULE_DIR_ . self::PACKLINK_ORDER_ICONS_TEMPLATE,
            $this->context->smarty
        )->fetch();
    }

    /**
     * Returns template that should be rendered in order draft column within orders table.
     *
     * @param string $reference Packlink shipment reference.
     *
     * @return string Rendered template output.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Packlink\BusinessLogic\Order\Exceptions\OrderNotFound
     */
    public function getOrderDraft($reference)
    {
        if ($reference === '') {
            return $reference;
        }

        \Packlink\PrestaShop\Classes\Bootstrap::init();

        /** @var \Packlink\PrestaShop\Classes\Repositories\OrderRepository $orderRepository */
        $orderRepository = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\Order\Interfaces\OrderRepository::CLASS_NAME
        );
        $orderDetails = $orderRepository->getOrderDetailsByReference($reference);

        $this->context->smarty->assign(array(
            'imgSrc' => _PS_BASE_URL_ . _MODULE_DIR_ . 'packlink/logo.png',
            'deleted' => $orderDetails->isDeleted(),
            'orderDraftLink' => $this->getOrderDraftUrl($reference),
        ));

        return $this->context->smarty->createTemplate(
            _PS_MODULE_DIR_ . self::PACKLINK_ORDER_DRAFT_TEMPLATE,
            $this->context->smarty
        )->fetch();
    }

    /**
     * Returns link to order draft on Packlink for the provided shipment reference.
     *
     * @param string $reference Shipment reference.
     *
     * @return string Link to order draft on Packlink.
     */
    private function getOrderDraftUrl($reference)
    {
        /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService $configService */
        $configService = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\Configuration::CLASS_NAME
        );
        $userCountry = $configService->getUserInfo() !== null
            ? \Tools::strtolower($configService->getUserInfo()->country)
            : 'es';

        return "https://pro.packlink.$userCountry/private/shipments/$reference";
    }

    /**
     * Validates provided order.
     *
     * @param \Order $order
     *
     * @return bool Returns true if order object is valid, otherwise returns false.
     */
    private function validateOrder($order)
    {
        static $valid_order_state = array();

        if (!Validate::isLoadedObject($order)) {
            return false;
        }

        if (!isset($valid_order_state[$order->current_state])) {
            $valid_order_state[$order->current_state] = Validate::isLoadedObject($order->getCurrentOrderState());
        }

        if (!$valid_order_state[$order->current_state]) {
            return false;
        }

        return true;
    }

    /**
     * Insert a value or key/value pair after a specific key in an array.  If key doesn't exist, value is appended
     * to the end of the array.
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
