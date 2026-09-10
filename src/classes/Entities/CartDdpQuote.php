<?php

namespace Packlink\PrestaShop\Classes\Entities;

use Logeecom\Infrastructure\ORM\Configuration\EntityConfiguration;
use Logeecom\Infrastructure\ORM\Configuration\IndexMap;
use Logeecom\Infrastructure\ORM\Entity;

/**
 * Class CartDdpQuote.
 *
 * Persists the render-time RAW duty base per cart so the order-validation request can reuse it
 * instead of re-quoting (DP3 amendment): validateOrder() re-prices shipping in a fresh request whose
 * per-request cache is empty, and a fresh two-call duty quote there would create an orphaned customs
 * invoice, risk a drifted total against the amount paid, and — on failure — leave a MANDATORY draft
 * without the selection it needs.
 *
 * The signature fingerprints everything the quote depends on that is a property of the CART
 * (destination, currency, declared value, line items); a stale row is simply ignored, never served.
 * The service is the other input Packlink prices from — it re-quotes at that service's own porterage
 * and ignores the freight we send — and it is a property of the CARRIER rather than the cart, so it
 * is kept as its own key instead: one row per cart per service. That way a carrier on its own service
 * gets its own quote rather than reusing another's, and re-pricing one carrier does not invalidate
 * the others.
 *
 * Only the raw base is stored — per-method merchant adjustments are applied at read time from each
 * method's own config, so an adjustment change takes effect without invalidating the quote.
 *
 * Stored in the shared packlink_entity table like CartDdpSelection, so there is no DDL.
 *
 * @package Packlink\PrestaShop\Classes\Entities
 */
class CartDdpQuote extends Entity
{
    /**
     * Fully qualified name of this class.
     */
    const CLASS_NAME = __CLASS__;
    /**
     * Type of the entity.
     */
    const TYPE = 'CartDdpQuote';
    /**
     * @var string
     */
    protected $cartId;
    /**
     * Fingerprint of the cart state the quote was made for.
     *
     * @var string
     */
    protected $signature;
    /**
     * The Packlink service the base was quoted for.
     *
     * Packlink prices duty from goods plus freight, and it discards the freight we declare in favour
     * of its own `porterage` for the chosen service - so the service, not the price a method shows, is
     * what makes one quote differ from another. Measured on FR->CH at one identical declared freight,
     * four services answered 87.12 / 86.71 / 87.04 / 88.58.
     *
     * This is therefore a second key, not a detail: one row per cart per service, so a carrier on its
     * own service cannot be served another's quote, and re-pricing one does not invalidate the rest.
     *
     * A string rather than an int because it is an identity, compared for equality.
     *
     * @var string
     */
    protected $serviceId;
    /**
     * Raw, unadjusted duty base in the cart currency.
     *
     * @var float
     */
    protected $rawBase;
    /**
     * Packlink's own carrier price for this service - the freight the base was actually quoted against.
     *
     * Kept because it is the ONLY place this figure is knowable from. A platform can only read it out
     * of a products response, and the draft is built in a later request that makes no such call. Without
     * it the draft has to guess the freight from what the shopper paid, which is porterage PLUS
     * Packlink's platform fee - measured on order #84 as 44.99 against a real carrier price of 44.00,
     * and the draft then quoted 26.49 where the shopper was charged 26.40.
     *
     * @var float
     */
    protected $porterage;
    /**
     * The checkout customs invoice this service was last quoted against, when there is one.
     *
     * Kept so the next quote for this cart and service can re-point that invoice with PUT instead of
     * creating another. Packlink offers neither a delete nor a list endpoint for `/v2/customs-invoices`,
     * so an invoice we abandon is permanent and invisible - and a shopper who edits their cart a few
     * times would otherwise leave one behind per edit per carrier.
     *
     * Independent of the signature: a stale signature means the AMOUNT must be re-quoted, not that the
     * invoice must be a new one. So the id survives a cart change and is reused for the re-quote,
     * which is exactly when it saves the most.
     *
     * @var string
     */
    protected $invoiceId;
    /**
     * List of entity fields.
     *
     * @var array
     */
    protected $fields = array('id', 'cartId', 'signature', 'serviceId', 'rawBase', 'invoiceId', 'porterage');

    /**
     * Returns entity configuration object.
     *
     * @return EntityConfiguration Configuration object.
     */
    public function getConfig()
    {
        $map = new IndexMap();
        $map->addStringIndex('cartId');

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
    public function getSignature()
    {
        return $this->signature;
    }

    /**
     * @param string $signature
     */
    public function setSignature($signature)
    {
        $this->signature = $signature;
    }

    /**
     * @return string
     */
    public function getServiceId()
    {
        return $this->serviceId;
    }

    /**
     * @param string $serviceId
     */
    public function setServiceId($serviceId)
    {
        $this->serviceId = $serviceId;
    }

    /**
     * @return float
     */
    public function getRawBase()
    {
        return $this->rawBase;
    }

    /**
     * @param float $rawBase
     */
    public function setRawBase($rawBase)
    {
        $this->rawBase = $rawBase;
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
     * @return string|null
     */
    public function getInvoiceId()
    {
        return $this->invoiceId;
    }

    /**
     * @param string|null $invoiceId
     */
    public function setInvoiceId($invoiceId)
    {
        $this->invoiceId = $invoiceId;
    }
}
