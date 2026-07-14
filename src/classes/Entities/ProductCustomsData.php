<?php

namespace Packlink\PrestaShop\Classes\Entities;

use Logeecom\Infrastructure\ORM\Configuration\EntityConfiguration;
use Logeecom\Infrastructure\ORM\Configuration\IndexMap;
use Logeecom\Infrastructure\ORM\Entity;

/**
 * Class ProductCustomsData
 *
 * Module-owned per-product customs attributes (PrestaShop products carry neither natively).
 * Stored in the shared `packlink_entity` table, keyed by product id. Fed into the core Item
 * during draft creation; empty values fall back to the configured customs mapping defaults.
 *
 * @package Packlink\PrestaShop\Classes\Entities
 */
class ProductCustomsData extends Entity
{
    /**
     * Fully qualified name of this class.
     */
    const CLASS_NAME = __CLASS__;
    /**
     * PrestaShop product ID.
     *
     * @var int
     */
    public $productId;
    /**
     * Harmonized System (HS) tariff number.
     *
     * @var string
     */
    public $hsCode;
    /**
     * ISO-3166-1 alpha-2 country of origin.
     *
     * @var string
     */
    public $countryOfOrigin;
    /**
     * Array of field names.
     *
     * @var array
     */
    protected $fields = array('id', 'productId', 'hsCode', 'countryOfOrigin');

    /**
     * Returns full class name.
     *
     * @return string Fully qualified class name.
     */
    public static function getClassName()
    {
        return static::CLASS_NAME;
    }

    /**
     * Returns entity configuration object.
     *
     * @return EntityConfiguration Configuration object.
     */
    public function getConfig()
    {
        $map = new IndexMap();
        $map->addIntegerIndex('productId');

        return new EntityConfiguration($map, 'ProductCustomsData');
    }
}
