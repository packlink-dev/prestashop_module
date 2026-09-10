<?php

namespace Packlink\PrestaShop\Classes\BusinessLogicServices;

use Packlink\BusinessLogic\Http\DTO\Draft;
use Packlink\BusinessLogic\Order\Objects\Order;
use Packlink\BusinessLogic\Order\OrderService as BaseService;

/**
 * Class OrderService
 *
 * @package Packlink\PrestaShop\Classes\BusinessLogicServices
 */
class OrderService extends BaseService
{
    /**
     * Prepares the draft, then declares the GOODS as its content value rather than the order total.
     *
     * Core sets `contentValue` from `$order->getTotalPrice()`, which ShopOrderService fills with
     * `total_paid_tax_incl` - goods PLUS shipping, and on a duties-paid order the duty as well. That is
     * the shipment's declared value, so over-declaring it prices Packlink's shipment protection off a
     * figure the contents are not worth and inflates the compensation basis a claim would be settled
     * against. Measured on order #83: 143.60 of goods and 71.39 of shipping were declared as 214.99,
     * and the protection offered covered 214.99.
     *
     * Corrected here rather than in core because the same `getTotalPrice()` also feeds
     * `addCashOnDeliveryDetails()`, and the COD amount MUST stay the order total - that is what the
     * customer hands over on delivery, and #83 was a COD order for exactly 214.99. One core value
     * serves two different meanings, so the platform fixes the one it can see is wrong; splitting them
     * belongs to core and is not a DDP change.
     *
     * Summed from the order items, which is the only source that is neither goods-plus-shipping nor
     * affected by the duty. Left alone when the sum is not positive: declaring 0.00 would be worse than
     * over-declaring, because a shipment worth nothing cannot be insured or compensated.
     *
     * The duty is untouched by this. Packlink prices it from the customs invoice's own per-item values
     * plus the carrier's porterage, not from `contentValue` - verified on #83, where the duty charged at
     * checkout and the duty Packlink billed were both 26.40 while `contentValue` carried 214.99.
     *
     * @param Order $order
     *
     * @return Draft Prepared shipment draft.
     *
     * @throws \Packlink\BusinessLogic\Order\Exceptions\EmptyOrderException When order has no items.
     */
    public function prepareDraft(Order $order)
    {
        $draft = parent::prepareDraft($order);

        $goods = 0.0;

        foreach ($order->getItems() as $item) {
            // Multiplied by the quantity, because on THIS platform getTotalPrice() is a UNIT price:
            // ShopOrderService fills it from `unit_price_tax_incl` (and getPrice() from
            // `unit_price_tax_excl`), which is also why the customs invoice declares 28.72 with a
            // quantity of 5 rather than 143.60. Shopify fills the same field from Shopify's
            // `originalTotalSet`, which IS a line total, so its own override must NOT multiply - the
            // two look like the same expression and are not. Do not "harmonise" them.
            $goods += (float)$item->getTotalPrice() * (int)$item->getQuantity();
        }

        if ($goods > 0.0) {
            $draft->contentValue = round($goods, 2);
        }

        return $draft;
    }
}
