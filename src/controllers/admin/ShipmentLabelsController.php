<?php

use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Order\Interfaces\OrderRepository;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

/**
 * Class ShipmentLabelsController
 */
class ShipmentLabelsController extends PacklinkBaseController
{
    /**
     * Sets shipment label to have been printed.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
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
