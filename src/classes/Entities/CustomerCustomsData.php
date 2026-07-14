<?php

namespace Packlink\PrestaShop\Classes\Entities;

use Logeecom\Infrastructure\ORM\Configuration\EntityConfiguration;
use Logeecom\Infrastructure\ORM\Configuration\IndexMap;
use Logeecom\Infrastructure\ORM\Entity;

/**
 * Class CustomerCustomsData
 *
 * Module-owned per-customer private-person Tax ID (PrestaShop has Address `vat_number` for
 * company VAT but no tax id for private persons). Stored in the shared `packlink_entity`
 * table, keyed by customer id. Feeds the customs receiver tax id during draft creation.
 *
 * @package Packlink\PrestaShop\Classes\Entities
 */
class CustomerCustomsData extends Entity
{
    /**
     * Fully qualified name of this class.
     */
    const CLASS_NAME = __CLASS__;
    /**
     * PrestaShop customer ID.
     *
     * @var int
     */
    public $customerId;
    /**
     * Private-person tax id.
     *
     * @var string
     */
    public $taxId;
    /**
     * Array of field names.
     *
     * @var array
     */
    protected $fields = array('id', 'customerId', 'taxId');

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
        $map->addIntegerIndex('customerId');

        return new EntityConfiguration($map, 'CustomerCustomsData');
    }
}
