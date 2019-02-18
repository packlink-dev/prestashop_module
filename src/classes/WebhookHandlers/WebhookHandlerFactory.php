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

namespace Packlink\PrestaShop\Classes\WebhookHandlers;

use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\Utility\Events\EventBus;

/**
 * Class WebhookHandlerFactory
 *
 * @package Packlink\PrestaShop\Classes\WebhookHandlers
 */
class WebhookHandlerFactory
{
    /**
     * Instantiates appropriate webhook handler for given event.
     *
     * @param string $event Name of the event.
     *
     * @return WebhookHandler
     */
    public static function create($event)
    {
        /** @var EventBus $eventBus */
        $eventBus = ServiceRegister::getService(EventBus::CLASS_NAME);

        switch ($event) {
            case 'shipment.carrier.success':
                return new ShipmentCarrierSuccessHandler($eventBus);
            case 'shipment.carrier.fail':
                return new ShipmentCarrierFailHandler($eventBus);
            case 'shipment.label.ready':
                return new ShipmentLabelReadyHandler($eventBus);
            case 'shipment.label.fail':
                return new ShipmentLabelFailHandler($eventBus);
            case 'shipment.tracking.update':
                return new ShipmentTrackingUpdateHandler($eventBus);
            case 'shipment.delivered':
                return new ShipmentDeliveredHandler($eventBus);
            default:
                return null;
        }
    }
}
