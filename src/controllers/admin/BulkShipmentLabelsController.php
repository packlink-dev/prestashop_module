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

use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Order\Interfaces\OrderRepository as OrderRepositoryInterface;
use Packlink\BusinessLogic\Order\Models\OrderShipmentDetails;
use Packlink\BusinessLogic\Utility\PdfMerge;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\Repositories\OrderRepository;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

/**
 * Class BulkShipmentLabelsController
 */
class BulkShipmentLabelsController extends ModuleAdminController
{
    /**
     * BulkShipmentLabelsController constructor.
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
     * Controller entry endpoint.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function initContent()
    {
        /** @var OrderRepository $orderRepository */
        $orderRepository = ServiceRegister::getService(OrderRepositoryInterface::CLASS_NAME);

        $this->bulkPrintLabels($orderRepository);
    }

    /**
     * Prints all available shipment labels in one merged PDF document.
     *
     * @param OrderRepository $orderRepository Order repository.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function bulkPrintLabels(OrderRepository $orderRepository)
    {
        $orderIds = \Tools::getValue('orders');

        $tmpDirectory = _PS_MODULE_DIR_ . 'packlink/tmp';
        if (!empty($orderIds) && !is_dir($tmpDirectory) && !mkdir($tmpDirectory)) {
            throw new \RuntimeException(
                TranslationUtility::__('Directory "%s" was not created', array($tmpDirectory))
            );
        }

        $this->saveFilesLocally($orderIds, $orderRepository);

        $result = false;

        try {
            $pdfs = array();
            $iterator = new \DirectoryIterator($tmpDirectory);
            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isDot()) {
                    $pdfs[] = $fileInfo->getPath() . '/' . $fileInfo->getFilename();
                }
            }

            $bulkLabelDirectory = _PS_MODULE_DIR_ . 'packlink/labels';
            if (!is_dir($bulkLabelDirectory) && !mkdir($bulkLabelDirectory)) {
                throw new \RuntimeException(
                    TranslationUtility::__('Directory "%s" was not created', array($bulkLabelDirectory))
                );
            }

            $now = date('Y-m-d');
            $outputPath = $bulkLabelDirectory . "/Packlink-bulk-shipment-labels_$now.pdf";
            $result = PdfMerge::merge($pdfs, $outputPath);
        } catch (\Exception $e) {
            Logger::logError(
                TranslationUtility::__('Unable to create bulk labels file'),
                'Integration'
            );
        }

        $this->deleteTemporaryFiles($tmpDirectory);

        if ($result !== false) {
            PacklinkPrestaShopUtility::dieInline($result);
        }

        PacklinkPrestaShopUtility::die400();
    }

    /**
     * Saves PDF files to temporary directory on the system.
     *
     * @param array $orderIds
     * @param OrderRepository $orderRepository
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function saveFilesLocally(array $orderIds, OrderRepository $orderRepository)
    {
        $orderDetailsRepository = RepositoryRegistry::getRepository(OrderShipmentDetails::getClassName());

        foreach ($orderIds as $orderId) {
            $orderDetails = $orderRepository->getOrderDetailsById((int)$orderId);
            if ($orderDetails !== null) {
                $labels = $orderDetails->getShipmentLabels();

                foreach ($labels as $label) {
                    if (!$label->isPrinted()) {
                        $label->setPrinted(true);
                    }

                    $this->savePDF($label->getLink());
                }

                $orderDetailsRepository->update($orderDetails);
            } else {
                Logger::logWarning(TranslationUtility::__('Order details not found'), 'Integration');
            }
        }
    }

    /**
     * Saves PDF file from provided URL to temporary location on the system.
     *
     * @param string $link
     */
    private function savePDF($link)
    {
        $file = fopen($link, 'rb');
        if ($file) {
            $tmpFile = fopen(_PS_MODULE_DIR_ . 'packlink/tmp/' . microtime() . '.pdf', 'wb');
            if ($tmpFile) {
                while (!feof($file)) {
                    fwrite($tmpFile, fread($file, 1024 * 8), 1024 * 8);
                }

                fclose($tmpFile);
            }

            fclose($file);
        }
    }

    /**
     * Deletes all temporary files created in the process of bulk printing of labels.
     *
     * @param string $tmpDirectory
     */
    private function deleteTemporaryFiles($tmpDirectory)
    {
        $it = new RecursiveDirectoryIterator($tmpDirectory, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator(
            $it,
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($tmpDirectory);
    }
}
