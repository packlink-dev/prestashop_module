<?php

namespace Packlink\PrestaShop\Classes\Entities;

use Logeecom\Infrastructure\ORM\Configuration\EntityConfiguration;
use Logeecom\Infrastructure\ORM\Configuration\IndexMap;
use Logeecom\Infrastructure\ORM\Entity;

/**
 * Class CartDdpSelection.
 *
 * Records that a shopper bought a duties-paid shipping option, and the duty amount actually charged.
 * The amount is captured at order validation from what was already quoted for the render — it is never
 * re-quoted, because a second lookup would both create another customs invoice and risk charging a
 * different figure than the shopper agreed to.
 *
 * Stored in the shared packlink_entity table like CartCarrierDropOffMapping, so there is no DDL.
 *
 * @package Packlink\PrestaShop\Classes\Entities
 */
class CartDdpSelection extends Entity
{
    /**
     * Fully qualified name of this class.
     */
    const CLASS_NAME = __CLASS__;
    /**
     * Type of the entity.
     */
    const TYPE = 'CartDdpSelection';
    /**
     * @var string
     */
    protected $cartId;
    /**
     * @var string
     */
    protected $orderId;
    /**
     * @var string
     */
    protected $carrierReferenceId;
    /**
     * Duty amount charged, in the order currency.
     *
     * @var float
     */
    protected $amount;
    /**
     * @var string
     */
    protected $currency;
    /**
     * Packlink's own carrier price for the service this order bought, when it is known.
     *
     * Recorded here because the cart's quote rows are dropped as soon as the selection is made, and the
     * draft is built in a later request that cannot ask Packlink for the figure again. It is what the
     * draft must declare as the shipment's transport cost: the price the shopper paid also contains
     * Packlink's platform fee, which the shopper legitimately pays but which is not carrier freight and
     * does not belong in a customs value.
     *
     * Null on a selection recorded before this was carried, and on one whose quote never produced it -
     * the draft then falls back to deriving the freight from what was paid.
     *
     * @var float
     */
    protected $porterage;
    /**
     * List of entity fields.
     *
     * @var array
     */
    protected $fields = array(
        'id',
        'cartId',
        'orderId',
        'carrierReferenceId',
        'amount',
        'currency',
        'porterage',
    );

    /**
     * Returns entity configuration object.
     *
     * @return EntityConfiguration Configuration object.
     */
    public function getConfig()
    {
        $map = new IndexMap();
        $map->addStringIndex('cartId');
        $map->addStringIndex('orderId');
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
    public function getOrderId()
    {
        return $this->orderId;
    }

    /**
     * @param string $orderId
     */
    public function setOrderId($orderId)
    {
        $this->orderId = $orderId;
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
     * @return float
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param float $amount
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
    }

    /**
     * @return float|null
     */
    public function getPorterage()
    {
        return $this->porterage;
    }

    /**
     * @param float|null $porterage
     */
    public function setPorterage($porterage)
    {
        $this->porterage = $porterage;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param string $currency
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }
}
