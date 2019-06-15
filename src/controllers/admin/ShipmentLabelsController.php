<?php

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
