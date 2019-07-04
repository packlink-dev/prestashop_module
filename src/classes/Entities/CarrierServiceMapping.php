<?php

namespace Packlink\PrestaShop\Classes\Entities;

use Logeecom\Infrastructure\ORM\Configuration\EntityConfiguration;
use Logeecom\Infrastructure\ORM\Configuration\IndexMap;
use Logeecom\Infrastructure\ORM\Entity;

class CarrierServiceMapping extends Entity
{
    /**
     * Fully qualified name of this class.
     */
    const CLASS_NAME = __CLASS__;
    /**
     * PrestaShop carrier reference ID.
     *
     * @var int
     */
    public $carrierReferenceId;
    /**
     * Packlink shipping method ID.
     *
     * @var int
     */
    public $methodId;
    /**
     * Array of field names.
     *
     * @var array
     */
    protected $fields = array('id', 'methodId', 'carrierReferenceId');

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
        $map->addIntegerIndex('carrierReferenceId')
            ->addIntegerIndex('methodId');

        return new EntityConfiguration($map, 'CarrierServiceMapping');
    }
}
