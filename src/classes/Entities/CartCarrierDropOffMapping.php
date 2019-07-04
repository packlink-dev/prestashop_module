<?php

namespace Packlink\PrestaShop\Classes\Entities;

use Logeecom\Infrastructure\ORM\Configuration\EntityConfiguration;
use Logeecom\Infrastructure\ORM\Configuration\IndexMap;
use Logeecom\Infrastructure\ORM\Entity;

class CartCarrierDropOffMapping extends Entity
{
    /**
     * Fully qualified name of this class.
     */
    const CLASS_NAME = __CLASS__;
    /**
     * Type of the entity.
     */
    const TYPE = 'CartCarrierDropOffMapping';
    /**
     * @var string
     */
    protected $cartId;
    /**
     * @var string
     */
    protected $carrierReferenceId;
    /**
     * @var array
     */
    protected $dropOff;
    /**
     * List of entity fields.
     *
     * @var array
     */
    protected $fields = array('id', 'cartId', 'carrierReferenceId', 'dropOff');

    /**
     * Returns entity configuration object.
     *
     * @return EntityConfiguration Configuration object.
     */
    public function getConfig()
    {
        $map = new IndexMap();
        $map->addStringIndex('cartId');
        $map->addStringIndex('carrierReferenceId');

        return new EntityConfiguration($map, self::TYPE);
    }

    /**
     * @return string
     */
    public function getCartId()
    {
        return $this->cartId;
    }

    /**
     * @param string $cartId
     */
    public function setCartId($cartId)
    {
        $this->cartId = $cartId;
    }

    /**
     * @return string
     */
    public function getCarrierReferenceId()
    {
        return $this->carrierReferenceId;
    }

    /**
     * @param string $carrierReferenceId
     */
    public function setCarrierReferenceId($carrierReferenceId)
    {
        $this->carrierReferenceId = $carrierReferenceId;
    }

    /**
     * @return array
     */
    public function getDropOff()
    {
        return $this->dropOff;
    }

    /**
     * @param array $dropOff
     */
    public function setDropOff($dropOff)
    {
        $this->dropOff = $dropOff;
    }
}
