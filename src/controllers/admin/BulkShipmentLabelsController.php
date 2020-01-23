<?php

use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\OrderShipmentDetails\OrderShipmentDetailsService;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

/**
 * Class BulkShipmentLabelsController
 */
class BulkShipmentLabelsController extends PacklinkBaseController
{
    const FILE_NAME = 'packlink_labels.pdf';

    /**
     * Controller entry endpoint.
     */
    public function initContent()
    {
        $result = false;

        try {
            $result = $this->bulkPrintLabels();
        } catch (\Exception $e) {
            Logger::logError(
                TranslationUtility::__('Unable to create bulk labels file'),
                'Integration'
            );
        }

        if ($result !== false) {
            // Filename is required because generated temp name is random.
            PacklinkPrestaShopUtility::dieInline($result, self::FILE_NAME);
        }

        PacklinkPrestaShopUtility::die400();
    }

    /**
     * Prints all available shipment labels in one merged PDF document.
     *
     * @return bool|string File path of the final merged PDF document; FALSE on error.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \iio\libmergepdf\Exception
     */
    protected function bulkPrintLabels()
    {
        $outputPath = false;
        $tmpDirectory = _PS_MODULE_DIR_ . 'packlink/tmp';
        $paths = $this->prepareAllLabels($tmpDirectory);

        if (count($paths) === 1) {
            return $paths[0];
        }

        $now = date('Y-m-d');
        $merger = new \iio\libmergepdf\Merger();
        $merger->addIterator($paths);
        $outputFile = $merger->merge();
        if (!empty($outputFile)) {
            $bulkLabelDirectory = _PS_MODULE_DIR_ . 'packlink/labels';
            /** @noinspection MkdirRaceConditionInspection */
            if (!is_dir($bulkLabelDirectory) && !mkdir($bulkLabelDirectory)) {
                throw new \RuntimeException(
                    TranslationUtility::__('Directory "%s" was not created', array($bulkLabelDirectory))
                );
            }

            $outputPath = $bulkLabelDirectory . "/Packlink-bulk-shipment-labels_$now.pdf";
            file_put_contents($outputPath, $outputFile);
        }

        \Tools::deleteDirectory($tmpDirectory);

        return $outputPath;
    }

    /**
     * @param string $tmpDirectory
     *
     * @return array
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    protected function prepareAllLabels($tmpDirectory)
    {
        $orderIds = \Tools::getValue('orders');

        /** @noinspection MkdirRaceConditionInspection */
        if (!empty($orderIds) && !is_dir($tmpDirectory) && !mkdir($tmpDirectory)) {
            throw new \RuntimeException(
                TranslationUtility::__('Directory "%s" was not created', array($tmpDirectory))
            );
        }

        return $this->saveFilesLocally($orderIds);
    }

    /**
     * Saves PDF files to temporary directory on the system.
     *
     * @param array $orderIds
     *
     * @return array An array of paths of the saved files.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    private function saveFilesLocally(array $orderIds)
    {
        $result = array();
        /** @var OrderShipmentDetailsService $shipmentDetailsService */
        $shipmentDetailsService = ServiceRegister::getService(OrderShipmentDetailsService::CLASS_NAME);
        /** @var \Packlink\BusinessLogic\Order\OrderService $orderService */
        $orderService = ServiceRegister::getService(\Packlink\BusinessLogic\Order\OrderService::CLASS_NAME);

        foreach ($orderIds as $orderId) {
            $shipmentDetails = $shipmentDetailsService->getDetailsByOrderId((int)$orderId);
            if ($shipmentDetails !== null) {
                $shipmentLabels = $shipmentDetails->getShipmentLabels();
                if (empty($shipmentLabels)) {
                    $shipmentLabels = $orderService->getShipmentLabels($shipmentDetails->getReference());
                    $shipmentDetailsService->setLabelsByReference($shipmentDetails->getReference(), $shipmentLabels);
                    $shipmentDetails->setShipmentLabels($shipmentLabels);
                }

                $labels = $shipmentDetails->getShipmentLabels();

                foreach ($labels as $label) {
                    $shipmentDetailsService->markLabelPrinted($shipmentDetails->getReference(), $label->getLink());

                    $path = $this->savePDF($label->getLink());
                    if (!empty($path)) {
                        $result[] = $path;
                    }
                }
            } else {
                Logger::logWarning(TranslationUtility::__('Order details not found'), 'Integration');
            }
        }

        return $result;
    }

    /**
     * Saves PDF file from provided URL to temporary location on the system.
     *
     * @param string $link Web link to the PDF file.
     *
     * @return string | boolean Path to the saved file
     */
    private function savePDF($link)
    {
        if (($data = \Tools::file_get_contents($link)) === false) {
            return $data;
        }

        $file = tempnam(sys_get_temp_dir(), 'packlink_pdf');
        file_put_contents($file, $data);

        return $file;
    }
}
