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

use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Order\Interfaces\OrderRepository;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

/**
 * Class ShipmentLabelsController
 */
class ShipmentLabelsController extends ModuleAdminController
{
    /**
     * ShipmentLabelsController constructor.
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
     * Sets shipment label to have been printed.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     */
    public function displayAjaxSetLabelPrinted()
    {
        $data = PacklinkPrestaShopUtility::getPacklinkPostData();

        if ($data['link'] && $data['orderId']) {
            /** @var \Packlink\PrestaShop\Classes\Repositories\OrderRepository $orderRepository */
            $orderRepository = ServiceRegister::getService(OrderRepository::CLASS_NAME);
            $orderRepository->setLabelPrinted($data['orderId'], $data['link']);
        }
    }
}
