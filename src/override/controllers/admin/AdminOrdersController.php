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
     * this is a cleanup of the overrides added in version 2.0.0.
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
     * @param int $orderId ID of the order.
     * @param array $tr Table row.
     *
     * @return string Rendered template output.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    public function printPDFIcons($orderId, $tr)
    {
        return $this->packlinkAdminOrderController->printPDFIcons($orderId, $this->context);
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
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function getOrderDraft($reference)
    {
        return $this->packlinkAdminOrderController->getOrderDraft($reference, $this->context);
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
}
