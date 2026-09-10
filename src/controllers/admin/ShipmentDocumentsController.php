<?php

use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\OrderShipmentDetails\OrderShipmentDetailsService;
use Packlink\BusinessLogic\ShipmentDocument\Interfaces\ShipmentDocumentServiceInterface;
use Packlink\BusinessLogic\ShipmentDocument\ShipmentDocumentType;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

/**
 * Class ShipmentDocumentsController
 */
class ShipmentDocumentsController extends PacklinkBaseController
{
    const FILE_NAME = 'packlink_document.pdf';

    /**
     * @var ShipmentDocumentServiceInterface
     */
    private $shipmentDocumentService;
    /**
     * @var OrderShipmentDetailsService
     */
    private $orderShipmentDetailsService;

    /**
     * Returns the list of documents for the provided order as JSON.
     */
    public function displayAjaxList()
    {
        $orderId = \Tools::getValue('orderId');
        if (!$orderId) {
            PacklinkPrestaShopUtility::die400(array('message' => 'Order ID missing'));
        }

        $documents = $this->getShipmentDocumentService()->getDocumentsForOrder((string)$orderId);

        PacklinkPrestaShopUtility::dieDtoEntities($documents);
    }

    /**
     * Streams the requested document as an attachment and marks it printed.
     */
    public function displayAjaxDownload()
    {
        $this->streamDocument(true);
    }

    /**
     * Streams the requested document inline and marks it printed.
     */
    public function displayAjaxPrint()
    {
        $this->streamDocument(false);
    }

    /**
     * Fetches the requested document, marks it printed and streams it back through the admin origin.
     *
     * @param bool $asAttachment Whether the document should be served as an attachment (download)
     *                           or inline (browser print).
     */
    private function streamDocument($asAttachment)
    {
        $orderId = \Tools::getValue('orderId');
        $type = \Tools::getValue('type');
        $link = \Tools::getValue('link');

        if (!$orderId || $type === false || $type === '' || !$link) {
            PacklinkPrestaShopUtility::die400(array('message' => 'Missing required parameters'));
        }

        if (!in_array($type, ShipmentDocumentType::getAll(), true)) {
            PacklinkPrestaShopUtility::die400(array('message' => 'Unknown document type'));
        }

        $shipmentDetails = $this->getOrderShipmentDetailsService()->getDetailsByOrderId((string)$orderId);
        if ($shipmentDetails === null) {
            PacklinkPrestaShopUtility::die404(array('message' => 'Order shipment details not found'));
        }

        try {
            $this->getShipmentDocumentService()->markDocumentPrinted(
                $shipmentDetails->getReference(),
                $type,
                $link
            );
        } catch (\Exception $e) {
            Logger::logError(
                TranslationUtility::__('Unable to mark document as printed') . ' ' . $e->getMessage(),
                'Integration'
            );
        }

        $data = \Tools::file_get_contents($this->resolveCurrentLink((string)$orderId, $type, $link));

        // An expired link is not a transport failure: the storage answers 403 with an error document,
        // which file_get_contents() returns as ordinary content. Streaming that as a PDF is how the
        // merchant ends up printing "ExpiredToken ... the provided token has expired" on a sheet of
        // paper, so the payload is checked for the PDF header instead of being trusted.
        if ($data === false || strpos(substr((string)$data, 0, 1024), '%PDF-') === false) {
            Logger::logError(
                TranslationUtility::__('Packlink returned no usable document for order') . ' ' . $orderId,
                'Integration'
            );

            PacklinkPrestaShopUtility::die500(array('message' => 'Unable to fetch document'));
        }

        $file = tempnam(sys_get_temp_dir(), 'packlink_pdf');
        file_put_contents($file, $data);

        if ($asAttachment) {
            PacklinkPrestaShopUtility::dieFile($file, self::FILE_NAME);
        }

        PacklinkPrestaShopUtility::dieInline($file, self::FILE_NAME);
    }

    /**
     * Returns a link that is valid right now for the requested document.
     *
     * The link the page carries was signed when the Packlink panel rendered, and Packlink's document
     * storage rejects it a couple of hours later - so any merchant who prints from a tab that has been
     * open for a while, or returns to an order later in the day, fetches an expired URL. Re-resolving
     * the document costs one API call and hands back a freshly signed link.
     *
     * Falls back to the submitted link whenever the document cannot be re-resolved, so a failure here
     * degrades to the previous behaviour rather than blocking the print.
     *
     * @param string $orderId Shop order id.
     * @param string $type Document type being printed.
     * @param string $link Link submitted by the page.
     *
     * @return string
     */
    private function resolveCurrentLink($orderId, $type, $link)
    {
        try {
            foreach ($this->getShipmentDocumentService()->getDocumentsForOrder($orderId) as $document) {
                if ($document->getType() !== $type) {
                    continue;
                }

                // An order carries exactly one customs invoice and its URL is re-signed on every read,
                // so the stored link never matches; labels are several per order and keep a stable
                // link, which is the only thing identifying which one was clicked.
                if ($type === ShipmentDocumentType::CUSTOMS_INVOICE || $document->getLink() === $link) {
                    return $document->getLink();
                }
            }
        } catch (\Exception $e) {
            Logger::logWarning(
                'Failed to refresh the document link for order ' . $orderId . ': ' . $e->getMessage(),
                'Integration'
            );
        }

        return $link;
    }

    /**
     * Returns an instance of shipment document service.
     *
     * @return ShipmentDocumentServiceInterface
     */
    private function getShipmentDocumentService()
    {
        if ($this->shipmentDocumentService === null) {
            $this->shipmentDocumentService = ServiceRegister::getService(
                ShipmentDocumentServiceInterface::CLASS_NAME
            );
        }

        return $this->shipmentDocumentService;
    }

    /**
     * Returns an instance of order shipment details service.
     *
     * @return OrderShipmentDetailsService
     */
    private function getOrderShipmentDetailsService()
    {
        if ($this->orderShipmentDetailsService === null) {
            $this->orderShipmentDetailsService = ServiceRegister::getService(
                OrderShipmentDetailsService::CLASS_NAME
            );
        }

        return $this->orderShipmentDetailsService;
    }
}
