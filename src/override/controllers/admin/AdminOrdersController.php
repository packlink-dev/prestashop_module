<?php

/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUnusedPrivateMethodInspection */
/** @noinspection PhpIncludeInspection */

/**
 * Class AdminOrdersController
 */
class AdminOrdersController extends AdminOrdersControllerCore
{
    /** OLD PART START */
    /**
     * ***** everything inside this block will be removed on install/upgrade ****
     * This is a cleanup of the overrides added previous version(s).
     */
    const PACKLINK_ORDER_DRAFT_TEMPLATE = '';
    const PACKLINK_ORDER_ICONS_TEMPLATE = '';

    private function getOrderDraftUrl()
    {
    }

    private function validateOrder()
    {
    }

    private function insertElementIntoArrayAfterSpecificKey()
    {
    }

    /** OLD PART END */
    /**
     * @var \Packlink\PrestaShop\Classes\Overrides\AdminOrdersController
     */
    private $packlinkAdminOrderController;
    /**
     * AdminOrdersController constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->initializePacklinkHandler();
    }

    /**
     * Renders invoice and shipment label icons.
     *
     * @param string $orderId ID of the order.
     * @param array $tr Table row.
     *
     * @return string Rendered template output.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     * @throws \SmartyException
     */
    public function printPDFIcons($orderId, $tr)
    {
        return $this->packlinkAdminOrderController->renderPdfIcons($orderId, $this->context);
    }

    /**
     * Returns template that should be rendered in order draft column within orders table.
     *
     * @param string $orderId ID of the order.
     *
     * @return string Rendered template output.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \SmartyException
     * @throws \PrestaShopException
     */
    public function getOrderDraft($orderId)
    {
        return $this->packlinkAdminOrderController->getOrderDraft($orderId, $this->context);
    }

    /**
     * Initializes Packlink module handler for extending order details page.
     */
    private function initializePacklinkHandler()
    {
        require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

        $this->packlinkAdminOrderController = new \Packlink\PrestaShop\Classes\Overrides\AdminOrdersController();

        $this->fields_list = $this->packlinkAdminOrderController->insertOrderColumn($this->_select, $this->fields_list);

        $this->packlinkAdminOrderController->addBulkActions($this->bulk_actions);
    }

    private function addPacklinkHiddenFields()
    {

    }
}
