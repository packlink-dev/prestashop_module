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

namespace Packlink\PrestaShop\Classes\Tasks;

use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\TaskExecution\Task;
use Logeecom\Infrastructure\Utility\TimeProvider;
use Packlink\BusinessLogic\Http\Proxy;
use Packlink\BusinessLogic\Order\Interfaces\OrderRepository;
use Packlink\BusinessLogic\ShippingMethod\Utility\ShipmentStatus;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

class UpgradeShopOrderDetailsTask extends Task
{
    /**
     * @var array
     */
    protected $orderReferenceMap;
    /**
     * @var \Packlink\PrestaShop\Classes\Repositories\OrderRepository
     */
    private $orderRepository;

    /**
     * UpgradeShopOrderDetailsTask constructor.
     *
     * @param array $orderReferenceMap
     */
    public function __construct(array $orderReferenceMap)
    {
        $this->orderReferenceMap = $orderReferenceMap;
    }

    /**
     * @inheritdoc
     */
    public function serialize()
    {
        return serialize($this->orderReferenceMap);
    }

    /**
     * @inheritdoc
     */
    public function unserialize($serialized)
    {
        $this->orderReferenceMap = unserialize($serialized);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $size = count($this->orderReferenceMap);
        if ($size === 0) {
            $this->reportProgress(100);

            return;
        }

        /** @var Proxy $proxy */
        $proxy = ServiceRegister::getService(Proxy::CLASS_NAME);
        /** @var TimeProvider $timeProvider */
        $timeProvider = ServiceRegister::getService(TimeProvider::CLASS_NAME);

        $progress = 0;
        $step = (int)($size / 10) + 1;

        foreach ($this->orderReferenceMap as $index => $map) {
            if ($index % $step === 0) {
                $progress += 9;
                $this->reportProgress($progress);
            }

            $orderDetails = json_decode($map['details'], true);
            $orderCreated = $timeProvider->deserializeDateString($orderDetails['date'], 'Y/m/d');

            if (!$this->setReference($map['id_order'], $map['draft_reference'])
                || $orderCreated < $timeProvider->getDateTime(strtotime('-60 days'))) {
                continue;
            }

            try {
                $shipment = $proxy->getShipment($map['draft_reference']);
            } catch (\Exception $e) {
                $shipment = null;
            }

            if ($shipment === null) {
                $this->setDeleted($map['draft_reference']);

                continue;
            }

            if (in_array(
                $orderDetails['state'],
                array(
                    'READY_TO_PRINT',
                    'READY_FOR_COLLECTION',
                    'IN_TRANSIT',
                    'DELIVERED',
                ),
                true
            )) {
                $this->setLabels($map['draft_reference'], $proxy);
            }

            $this->setShipmentStatus($map['draft_reference'], $shipment);
            $this->setTrackingInfo($map['draft_reference'], $proxy, $shipment);
        }

        $this->reportProgress(100);
    }

    /**
     * Creates reference.
     *
     * @param string $orderId
     * @param string $referenceId
     *
     * @return bool
     */
    protected function setReference($orderId, $referenceId)
    {
        try {
            $this->getOrderRepository()->setReference($orderId, $referenceId);
        } catch (\Exception $e) {
            Logger::logError(
                TranslationUtility::__('Failed to create reference for order %d', array($orderId)),
                'Integration'
            );

            return false;
        }

        return true;
    }

    /**
     * Sets labels for order.
     *
     * @param string $reference
     * @param Proxy $proxy
     */
    protected function setLabels($reference, $proxy)
    {
        try {
            $labels = $proxy->getLabels($reference);
            $this->getOrderRepository()->setLabelsByReference($reference, $labels);
        } catch (\Exception $e) {
            Logger::logError(
                TranslationUtility::__('Failed to set labels for order with reference %s', array($reference)),
                'Integration'
            );
        }
    }

    /**
     * Sets tracking info for order.
     *
     * @param string $reference
     * @param Proxy $proxy
     * @param \Packlink\BusinessLogic\Http\DTO\Shipment $shipment
     */
    protected function setTrackingInfo($reference, $proxy, $shipment)
    {
        try {
            $trackingInfo = $proxy->getTrackingInfo($reference);
            $this->getOrderRepository()->updateTrackingInfo($reference, $trackingInfo, $shipment);
        } catch (\Exception $e) {
            Logger::logError(
                TranslationUtility::__(
                    'Failed to set tracking info for order with reference %s',
                    array($reference)
                ),
                'Integration'
            );
        }
    }

    /**
     * Sets order status.
     *
     * @param string $reference
     * @param $shipment
     */
    protected function setShipmentStatus($reference, $shipment)
    {
        try {
            $this->getOrderRepository()->setShippingStatusByReference(
                $reference,
                ShipmentStatus::getStatus($shipment->status)
            );
        } catch (\Exception $e) {
            Logger::logError(
                TranslationUtility::__('Order with reference %s not found.', array($reference)),
                'Integration'
            );
        }
    }

    /**
     * Marks order with provided reference as deleted on the system.
     *
     * @param string $reference
     */
    protected function setDeleted($reference)
    {
        try {
            $this->getOrderRepository()->setDeleted($reference);
        } catch (\Exception $e) {
            Logger::logError(
                TranslationUtility::__('Order with reference %s not found.', array($reference)),
                'Integration'
            );
        }
    }

    /**
     * Returns an instance of order repository service.
     *
     * @return \Packlink\PrestaShop\Classes\Repositories\OrderRepository
     */
    private function getOrderRepository()
    {
        if ($this->orderRepository === null) {
            $this->orderRepository = ServiceRegister::getService(OrderRepository::CLASS_NAME);
        }

        return $this->orderRepository;
    }
}
