<?php

namespace Packlink\PrestaShop\Classes\Utility;

use Logeecom\Infrastructure\Http\HttpClient;
use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Configuration;
use Packlink\BusinessLogic\OrderShipmentDetails\OrderShipmentDetailsService;

/**
 * Class CustomsInvoiceSynchronizer
 *
 * Keeps the stored customs invoice id in step with the one that currently exists at Packlink.
 *
 * The module records an invoice id only at the moment it creates the invoice itself, during draft
 * creation. A merchant who completes or edits customs in the Packlink UI gets a different - newer -
 * invoice, and nothing brought that id back: the order page then had no id to render and showed no
 * Customs row at all (observed on shipment FR2026PRO0002330628, whose invoice f7871736 was COMPLETED
 * at Packlink while the module had none).
 *
 * Packlink is authoritative here, so whatever invoice its shipment currently points at wins - that is
 * the last one created for the shipment. Core's Shipment DTO carries no customs data, so the id has to
 * be read from the shipment endpoint directly.
 *
 * @package Packlink\PrestaShop\Classes\Utility
 */
class CustomsInvoiceSynchronizer
{
    /**
     * Reads the customs invoice currently attached to the shipment at Packlink and stores it when it
     * differs from what is held locally.
     *
     * Safe to call on every shipment refresh: it exits without a request when the shipment reference
     * is unknown or the module is not authorised, and it never clears a stored id just because a
     * single request failed.
     *
     * @param string $reference Packlink shipment reference.
     *
     * @return bool True when a new id was stored.
     */
    public static function sync($reference)
    {
        $reference = trim((string)$reference);
        if ($reference === '') {
            return false;
        }

        try {
            /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService $configService */
            $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
            $token = $configService->getAuthorizationToken();
            if (empty($token)) {
                return false;
            }

            $remoteId = self::fetchInvoiceId($reference, $token);
            if ($remoteId === '') {
                // No customs invoice at Packlink (domestic shipment, or not created yet). Leave any
                // stored id alone - a shipment never loses its invoice.
                return false;
            }

            /** @var OrderShipmentDetailsService $detailsService */
            $detailsService = ServiceRegister::getService(OrderShipmentDetailsService::CLASS_NAME);
            $details = $detailsService->getDetailsByReference($reference);
            if ($details === null) {
                return false;
            }

            if ((string)$details->getCustomsInvoiceId() === $remoteId) {
                return false;
            }

            $detailsService->updateShipmentCustomsData($reference, $remoteId);

            Logger::logDebug(
                'Stored customs invoice ' . $remoteId . ' for shipment ' . $reference
                . ' (was "' . $details->getCustomsInvoiceId() . '").',
                'Integration'
            );

            return true;
        } catch (\Exception $e) {
            Logger::logWarning(
                'Failed to synchronise the customs invoice for shipment ' . $reference . ': ' . $e->getMessage(),
                'Integration'
            );

            return false;
        }
    }

    /**
     * Returns the customs invoice id Packlink currently reports for the shipment, or an empty string.
     *
     * @param string $reference
     * @param string $token
     *
     * @return string
     */
    private static function fetchInvoiceId($reference, $token)
    {
        /** @var HttpClient $client */
        $client = ServiceRegister::getService(HttpClient::CLASS_NAME);

        $response = $client->request(
            'GET',
            'https://api.packlink.com/v1/shipments/' . rawurlencode($reference),
            array('Authorization: ' . $token, 'Content-Type: application/json')
        );

        if ((int)$response->getStatus() !== 200) {
            return '';
        }

        $body = json_decode($response->getBody(), true);

        if (!is_array($body) || empty($body['customs']['customs_invoice_id'])) {
            return '';
        }

        return (string)$body['customs']['customs_invoice_id'];
    }
}
