<?php

namespace Packlink\PrestaShop\Classes\Utility;

use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Packlink\PrestaShop\Classes\Entities\CustomerCustomsData;
use Packlink\PrestaShop\Classes\Entities\ProductCustomsData;

/**
 * Class CustomsDataProvider.
 *
 * Single place to read the module-owned customs entities (product HS code / country of origin and
 * the customer tax id). Shared by the product/customer admin hooks (packlink.php) and the order
 * build (ShopOrderService) so the lookup is written once.
 *
 * @package Packlink\PrestaShop\Classes\Utility
 */
class CustomsDataProvider
{
    /**
     * Returns the stored customs data for a product, or null when none exists.
     *
     * @param int $productId
     *
     * @return ProductCustomsData|null
     */
    public static function getProductCustomsData($productId)
    {
        try {
            $repository = RepositoryRegistry::getRepository(ProductCustomsData::CLASS_NAME);

            $query = new QueryFilter();
            $query->where('productId', '=', (int)$productId);

            /** @var ProductCustomsData|null $data */
            $data = $repository->selectOne($query);

            return $data;
        } catch (\Exception $e) {
            Logger::logWarning(
                'Failed to read customs data for product ' . (int)$productId . ': ' . $e->getMessage(),
                'Integration'
            );

            return null;
        }
    }

    /**
     * Returns the stored private-person tax id for a customer, or an empty string.
     *
     * @param int $customerId
     *
     * @return string
     */
    public static function getCustomerTaxId($customerId)
    {
        try {
            $repository = RepositoryRegistry::getRepository(CustomerCustomsData::CLASS_NAME);

            $query = new QueryFilter();
            $query->where('customerId', '=', (int)$customerId);

            /** @var CustomerCustomsData|null $data */
            $data = $repository->selectOne($query);

            return ($data !== null && !empty($data->taxId)) ? $data->taxId : '';
        } catch (\Exception $e) {
            Logger::logWarning(
                'Failed to read customs tax id for customer ' . (int)$customerId . ': ' . $e->getMessage(),
                'Integration'
            );

            return '';
        }
    }
}
