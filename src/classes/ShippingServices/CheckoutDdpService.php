<?php

namespace Packlink\PrestaShop\Classes\ShippingServices;

use Cart;
use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ORM\QueryFilter\Operators;
use Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\DDP\DdpBehavior;
use Packlink\BusinessLogic\DDP\DdpCostComposer;
use Packlink\BusinessLogic\DDP\Interfaces\DdpCostServiceInterface;
use Packlink\BusinessLogic\DDP\Models\DdpCostResponse;
use Packlink\BusinessLogic\ShippingMethod\Models\ShippingMethod;
use Packlink\PrestaShop\Classes\Entities\CartDdpQuote;
use Packlink\PrestaShop\Classes\Utility\CachingUtility;

/**
 * Class CheckoutDdpService.
 *
 * Owns the duty lookup at checkout. PrestaShop calls getPackageShippingCost() once per carrier, while
 * a core getDdpCosts() is two HTTP requests whose first call creates a customs invoice — so the lookup
 * must happen once per render, not once per carrier, and the result is cached per request PLUS in a
 * cart-keyed server-side CartDdpQuote row (DP3 amendment). validateOrder() re-prices shipping in a
 * fresh request whose per-request cache is empty; the persisted raw base lets that request reuse the
 * render-time quote — same figure the shopper agreed to, no orphaned customs invoice — instead of
 * re-quoting. A signature over the quote's inputs guards the row: on any relevant cart change the row
 * mismatches and is refreshed by a new lookup. Only the raw base is persisted; per-method merchant
 * adjustments are applied at read time from each method's own config, so an adjustment change takes
 * effect without invalidating the quote.
 *
 * One lookup per PACKLINK SERVICE, not per cart and not per price. Packlink prices duty from goods
 * plus freight, and it does not use the freight we declare — it re-prices at its own `porterage` for
 * the service. Measured on FR->CH with one identical declared freight across four services: duties
 * 87.12 / 86.71 / 87.04 / 88.58. And ten declared freights against ONE service: all 87.65. So the
 * service is the thing that separates one answer from another, and the price a method happens to show
 * is not.
 *
 * That is N lookups for the N services a cart can ship on, dispatched together rather than one after
 * another: core runs the invoices as one concurrent wave and the products calls as a second, so the
 * render waits for the slowest lookup instead of the sum of them (four real carriers measured 3840 ms
 * against 11382 ms sequential). It is N per cart change rather than per render — the per-request cache
 * covers one render and the CartDdpQuote rows cover every later request for an unchanged cart.
 *
 * Concurrent, never batched: the products endpoint accepts an array but its responses carry no
 * service_id, mis-attribute packlink_reference and ignore request order, which yields plausible but
 * silently wrong prices. Separate concurrent calls each carry their own response and need no
 * correlation key at all.
 *
 * Each row also remembers the checkout customs invoice its service was quoted against, so a re-quote
 * re-points that invoice with PUT instead of creating another. Packlink offers neither a delete nor a
 * list endpoint for them, so an abandoned invoice is permanent and invisible — and a shopper editing
 * their cart would otherwise leave one behind per edit per carrier.
 *
 * The merchant adjustment is not a property of the service: each method carries its own type/amount,
 * applied per method on top of its group's base.
 *
 * @package Packlink\PrestaShop\Classes\ShippingServices
 */
class CheckoutDdpService
{
    /**
     * Returns the composed, adjusted duty amount for a shipping method, or null when none is available.
     *
     * Null covers every "no usable amount" case — inapplicable route, no eligible service, API failure —
     * because callers must not present a missing duty as a zero one (DP5).
     *
     * @param Cart $cart PrestaShop cart.
     * @param int $methodId Packlink shipping method id.
     * @param array $products Non-virtual cart product rows.
     *
     * @return float|null
     */
    public static function getAdjustedAmount(Cart $cart, $methodId, array $products)
    {
        $costs = CachingUtility::getDdpCosts();

        if ($costs === false) {
            $costs = self::fetchAll($cart, $products);
            CachingUtility::setDdpCosts($costs);
        }

        return isset($costs[$methodId]) ? $costs[$methodId] : null;
    }

    /**
     * Packlink's own carrier price for the service a method ships on, as this cart's quote learned it.
     *
     * Read from the persisted quote because it is knowable nowhere else: `porterage` only ever appears
     * in a products response, and the draft is assembled in a later request that makes no such call.
     * The caller must therefore take it BEFORE the cart's quote rows are dropped.
     *
     * It matters because the draft has to declare a transport cost on the real customs invoice, and the
     * obvious figure - what the shopper paid for shipping, less the duty inside it - is porterage PLUS
     * Packlink's platform fee. Measured on order #84: 44.99 against a carrier price of 44.00, which had
     * the draft screen quote 26.49 where the shopper was charged, and Packlink billed, 26.40.
     *
     * @param Cart $cart PrestaShop cart.
     * @param int $methodId Packlink shipping method id.
     *
     * @return float|null Null when this cart never quoted that method's service, or the quote did not
     *                    yield a carrier price.
     */
    public static function getQuotedPorterage(Cart $cart, $methodId)
    {
        try {
            $warehouse = CachingUtility::getDefaultWarehouse();
            $fromCountry = $warehouse !== null ? strtoupper((string)$warehouse->country) : '';
            $toCountry = self::getCartCountryIso($cart);

            $repository = RepositoryRegistry::getRepository(ShippingMethod::getClassName());
            $filter = new QueryFilter();
            $filter->where('id', Operators::EQUALS, (int)$methodId);

            /** @var ShippingMethod[] $methods */
            $methods = $repository->select($filter);

            if (empty($methods)) {
                return null;
            }

            $serviceId = self::findRouteServiceId($methods[0], $fromCountry, $toCountry);

            if ($serviceId === null) {
                return null;
            }

            $rows = self::loadQuotes($cart);

            if (!isset($rows[(string)$serviceId])) {
                return null;
            }

            $porterage = $rows[(string)$serviceId]->getPorterage();

            return ($porterage === null || (float)$porterage <= 0.0) ? null : (float)$porterage;
        } catch (\Exception $e) {
            Logger::logWarning(
                'Failed to read the quoted carrier price for method ' . (int)$methodId . ': ' . $e->getMessage(),
                'Integration'
            );

            return null;
        }
    }

    /**
     * Whether duty applies to this cart at all, i.e. the shipment crosses a border.
     *
     * A domestic cart is not a failure: no duty is owed, so the DDP option is simply not offered and
     * the base option prices normally whatever the merchant's behaviour setting says.
     *
     * @param Cart $cart PrestaShop cart.
     *
     * @return bool
     */
    public static function isApplicable(Cart $cart)
    {
        try {
            $warehouse = CachingUtility::getDefaultWarehouse();
            if ($warehouse === null) {
                return false;
            }

            if (empty($cart->id_address_delivery)) {
                return false;
            }

            $address = CachingUtility::getAddress((int)$cart->id_address_delivery);
            $country = CachingUtility::getCountry((int)$address->id_country);

            if (empty($country->iso_code)) {
                return false;
            }

            return strtoupper($country->iso_code) !== strtoupper($warehouse->country);
        } catch (\Exception $e) {
            Logger::logWarning('Failed to resolve DDP applicability: ' . $e->getMessage(), 'Integration');

            return false;
        }
    }

    /**
     * Decides whether the base (transport-only) carrier of a method must be withheld.
     *
     * Single predicate consulted by both the base and the DDP branch, so the two rows cannot disagree:
     *  - MANDATORY: the base option is never offered; if duty is unavailable the service disappears;
     *  - ENFORCED: the base option is withheld only while duty is actually available, otherwise it is
     *    the transport-only fallback (DP5);
     *  - OPTIONAL / NONE: the base option is always offered.
     *
     * @param string $behavior Effective DDP behaviour of the method.
     * @param bool $dutyAvailable Whether a usable duty amount was obtained.
     *
     * @return bool
     */
    public static function shouldHideBaseCarrier($behavior, $dutyAvailable)
    {
        if ($behavior === DdpBehavior::MANDATORY) {
            return true;
        }

        if ($behavior === DdpBehavior::ENFORCED) {
            return (bool)$dutyAvailable;
        }

        return false;
    }

    /**
     * Prices every eligible method: one duty lookup per Packlink service, all of them at once.
     *
     * One quote per service, because that is what the duty depends on — see groupByService() for the
     * measurements. Methods sharing a service share one lookup and therefore one customs invoice; a
     * method on its own service gets its own.
     *
     * The lookups are then dispatched together rather than one after another. Each is two Packlink
     * calls, doubled by the porterage correction, so four services cost ~11 s sequentially — inside a
     * checkout render. They are not a chain, though: the invoices are independent of each other and
     * each products call depends only on its own invoice, so core runs each stage as one concurrent
     * wave and the wall time becomes the slowest lookup rather than the sum (four real carriers
     * measured 3840 ms against 11382 ms, with identical amounts on all four).
     *
     * The merchant adjustment is per-method (each ShippingMethod carries its own type/amount), so it
     * is applied at the end from each method's own fields — never from the core response, which only
     * holds the adjustment of the one method that owned the quoted service id.
     *
     * @param Cart $cart PrestaShop cart.
     * @param array $products Non-virtual cart product rows.
     *
     * @return array Adjusted amounts keyed by method id; empty when duty is unavailable.
     */
    private static function fetchAll(Cart $cart, array $products)
    {
        if (!self::isApplicable($cart)) {
            return array();
        }

        $eligible = self::getEligibleMethods();
        if (empty($eligible)) {
            return array();
        }

        // Reuse the persisted render-time base when the cart still matches it, so the fresh request
        // validateOrder() prices in never re-quotes (no second customs invoice, no price drift). Any
        // persistence failure degrades to the API call — it must never break pricing.
        $signature = self::getQuoteSignature($cart, $products);
        $rows = self::loadQuotes($cart);
        $groups = self::groupByService($cart, $eligible);

        $bases = array();
        $items = array();

        foreach ($groups as $serviceId => $methods) {
            $existing = isset($rows[$serviceId]) ? $rows[$serviceId] : null;

            // A row is only usable when it carries a base: an invoice-only row exists precisely
            // because its quote failed, and reading its null base as 0.00 would put a free duties
            // option on the page.
            if ($existing !== null
                && $signature !== null
                && $existing->getSignature() === $signature
                && $existing->getRawBase() !== null
            ) {
                $bases[$serviceId] = (float)$existing->getRawBase();
                continue;
            }

            $item = self::buildLookup($cart, $serviceId, self::firstGuessFreight($methods), $existing);

            if ($item !== null) {
                $items[$serviceId] = $item;
            }
        }

        if (!empty($items)) {
            $bases = self::runLookups($cart, $items, $signature, $rows, $bases);
        }

        $costs = array();

        foreach ($groups as $serviceId => $methods) {
            if (!isset($bases[$serviceId])) {
                // This service could not be quoted. The others are independent lookups, so only the
                // methods on this one go without a duty.
                continue;
            }

            foreach ($methods as $methodId => $method) {
                $costs[$methodId] = DdpCostComposer::applyAdjustment(
                    $bases[$serviceId],
                    $method->getDdpAdjustmentType(),
                    $method->getDdpAdjustmentAmount()
                );
            }
        }

        return $costs;
    }

    /**
     * Groups the eligible methods by the Packlink service that will carry them on this route.
     *
     * The service is what the duty actually depends on. Packlink prices the duty from goods plus
     * freight, and it does NOT use the freight we declare: it re-prices at its own `porterage` for the
     * chosen service, so whatever a platform sends is overwritten. Measured on FR->CH with one
     * identical declared freight of 30.00 across four services:
     *
     *     service 23705  porterage 29.61  duty 87.12
     *     service 10136  porterage 25.67  duty 86.71
     *     service 10074  porterage 29.03  duty 87.04
     *     service 10059  porterage 44.00  duty 88.58
     *
     * Four services, four duties, one declared freight. And the converse, ten declared freights from
     * 20.00 to 59.33 against ONE service, all returned porterage 34.86 and duty 87.65.
     *
     * So grouping by the declared freight was a proxy that fails in both directions. Two methods a
     * merchant prices identically - one fixed-price pricing policy is enough - would share a lookup
     * and both be charged whichever service the group happened to pick, up to 1.87 out on the figures
     * above. And one service shown at several prices was looked up several times for an answer that
     * provably converges, each lookup costing four API calls and a permanent customs invoice.
     *
     * Keyed by service id as a string, because it is an identity compared for equality.
     *
     * A method with no DDP service on this cart's route gets no key and therefore no duty. That is the
     * same outcome the freight grouping reached through a null lookup, arrived at without the lookup.
     *
     * @param Cart $cart PrestaShop cart.
     * @param ShippingMethod[] $eligible Eligible methods keyed by method id.
     *
     * @return array Service id => methods keyed by method id.
     */
    private static function groupByService(Cart $cart, array $eligible)
    {
        $warehouse = CachingUtility::getDefaultWarehouse();
        $fromCountry = $warehouse !== null ? strtoupper((string)$warehouse->country) : '';
        $toCountry = self::getCartCountryIso($cart);

        $groups = array();

        foreach ($eligible as $methodId => $method) {
            $serviceId = self::findRouteServiceId($method, $fromCountry, $toCountry);

            if ($serviceId === null) {
                continue;
            }

            $groups[(string)$serviceId][$methodId] = $method;
        }

        return $groups;
    }

    /**
     * A first-guess freight for a group, read from whatever this request priced for one of its methods.
     *
     * Deliberately a guess. Core discards it and re-quotes at the service's own porterage, so its only
     * job is to make the first call well-formed; any member of the group will do, because they all
     * share the service whose porterage replaces it. Null when nothing was priced - core then falls
     * back to its own behaviour rather than being handed another carrier's price.
     *
     * @param ShippingMethod[] $methods Methods sharing a service, keyed by method id.
     *
     * @return float|null
     */
    private static function firstGuessFreight(array $methods)
    {
        $calculated = CachingUtility::getCosts();

        if (!is_array($calculated)) {
            return null;
        }

        foreach ($methods as $methodId => $ignored) {
            if (isset($calculated[$methodId])) {
                return (float)$calculated[$methodId];
            }
        }

        return null;
    }

    /**
     * Destination country of the cart's delivery address, upper-cased.
     *
     * @param Cart $cart PrestaShop cart.
     *
     * @return string ISO-2 code, or an empty string when it cannot be resolved.
     */
    private static function getCartCountryIso(Cart $cart)
    {
        try {
            if (empty($cart->id_address_delivery)) {
                return '';
            }

            $address = CachingUtility::getAddress((int)$cart->id_address_delivery);
            $country = CachingUtility::getCountry((int)$address->id_country);

            return empty($country->iso_code) ? '' : strtoupper((string)$country->iso_code);
        } catch (\Exception $e) {
            Logger::logWarning('Failed to resolve the cart destination country: ' . $e->getMessage(), 'Integration');

            return '';
        }
    }

    /**
     * Prepares one group's lookup, or null when this group cannot be quoted.
     *
     * The service is settled by the grouping, so it arrives here rather than being hunted for. The
     * freight is only a first guess for the same reason: core re-prices at this service's porterage
     * and discards whatever we sent, which is exactly why the service and not the freight is what
     * separates one lookup from another.
     *
     * A null return skips this group alone. The other groups are independent lookups and are still
     * worth dispatching.
     *
     * @param Cart $cart PrestaShop cart.
     * @param string|int $serviceId Packlink service this group ships on, from groupByService().
     * @param float|null $transport First-guess freight for the invoice's shipment cost (C8). Null when
     *                             pricing did not run this request; core then falls back to its own
     *                             behaviour rather than being handed another carrier's price.
     * @param CartDdpQuote|null $existing Row already stored for this cart and service, when there is
     *                                    one — read for the invoice id to re-point.
     *
     * @return array|null Lookup for core: 'order', 'serviceId' and 'invoiceId'.
     */
    private static function buildLookup(Cart $cart, $serviceId, $transport, $existing)
    {
        try {
            $warehouse = CachingUtility::getDefaultWarehouse();
            $order = CheckoutOrderFactory::fromCart($cart, $warehouse, $transport);

            // Duty is computed from the declared value, so a cart that priced to zero cannot yield a
            // meaningful amount. Bail rather than send it: Packlink would answer zero duty, which is
            // indistinguishable from a free DDP option and would be presented as one.
            if ((float)$order->getTotalPrice() <= 0.0) {
                Logger::logWarning(
                    'Skipping DDP lookup: cart declared value is zero, so no duty can be quoted.',
                    'Integration'
                );

                return null;
            }

            $invoiceId = $existing !== null ? $existing->getInvoiceId() : null;

            return array(
                'order' => $order,
                'serviceId' => $serviceId,
                // An invoice already made for this cart and service is re-pointed with PUT instead of
                // a new one being created. Packlink offers no way to delete or even list checkout
                // invoices, so every one abandoned here is permanent and invisible.
                'invoiceId' => ($invoiceId === null || $invoiceId === '') ? null : (string)$invoiceId,
            );
        } catch (\Exception $e) {
            Logger::logWarning('Failed to prepare a DDP lookup: ' . $e->getMessage(), 'Integration');

            return null;
        }
    }

    /**
     * This method's DDP service on the cart's own route, or null when it has none.
     *
     * Service rows are per route, so the id must be matched on the cart's own departure and
     * destination — the same match ShippingCostCalculator makes when it prices the method. Taking any
     * DDP-capable service instead sends Packlink a service that does not serve the destination, and it
     * answers 500: one method can carry id 10059 for DE/CH/GB and 30297 for US, and a nearby route
     * working is luck, not correctness.
     *
     * The first match wins. A method carrying more than one DDP service for a single route is a shape
     * Packlink has not produced on any account seen here — every method mapped one-to-one — and there
     * would be nothing to choose between them anyway: PrestaShop shows the method at one price, and
     * which service ships it is Packlink's decision at draft time, not ours.
     *
     * @param ShippingMethod $method Shipping method.
     * @param string $fromCountry Warehouse country, upper-cased.
     * @param string $toCountry Cart destination country, upper-cased.
     *
     * @return string|int|null
     */
    private static function findRouteServiceId(ShippingMethod $method, $fromCountry, $toCountry)
    {
        foreach ($method->getShippingServices() as $service) {
            if ($service->ddpSupportLevel !== null
                && strtoupper((string)$service->departureCountry) === $fromCountry
                && strtoupper((string)$service->destinationCountry) === $toCountry
            ) {
                return $service->serviceId;
            }
        }

        return null;
    }

    /**
     * Runs every prepared lookup as one batch and returns the bases it produced.
     *
     * @param Cart $cart PrestaShop cart.
     * @param array $items Lookups keyed by service id.
     * @param string|null $signature Fingerprint of the quoted cart state; null when it could not be
     *                               computed, in which case nothing is persisted.
     * @param CartDdpQuote[] $rows Rows already stored for this cart, keyed by service id.
     * @param array $bases Bases already satisfied from persistence, keyed by service id.
     *
     * @return array $bases, plus a base for every service that quoted successfully.
     */
    private static function runLookups(Cart $cart, array $items, $signature, array $rows, array $bases)
    {
        $currencyIso = self::getCartCurrencyIso($cart);

        try {
            $results = self::lookupMany($items);
        } catch (\Exception $e) {
            Logger::logWarning('Failed to fetch DDP costs at checkout: ' . $e->getMessage(), 'Integration');

            return $bases;
        }

        foreach ($items as $serviceId => $ignored) {
            if (!isset($results[$serviceId])) {
                continue;
            }

            $result = $results[$serviceId];
            $response = isset($result['costs']) ? $result['costs'] : null;
            $invoiceId = isset($result['invoiceId']) ? $result['invoiceId'] : null;

            // Core re-quotes at the service's own porterage and sets it on the order it was handed, so
            // after the call this IS Packlink's carrier price. Read here because it is the only moment
            // it is knowable: the draft is built in a later request that makes no products call.
            $porterage = (float)$items[$serviceId]['order']->getShippingCost();

            // Null base means no enabled component: Packlink quotes no duty on this route for this
            // service. An ordinary answer, and not the same thing as a duty of 0.00 — see core's
            // composeBase().
            $base = $response === null ? null : DdpCostComposer::composeBase($response);

            if ($base !== null) {
                $refusal = self::currencyRefusal($response, $currencyIso);

                if ($refusal !== null) {
                    Logger::logWarning('Discarding DDP costs: ' . $refusal . '.', 'Integration');
                    $base = null;
                }
            }

            if ($base !== null) {
                $bases[$serviceId] = (float)$base;
            } elseif (!empty($result['error'])) {
                Logger::logWarning(
                    'DDP costs unavailable for service ' . $serviceId . ': ' . $result['error'] . '.',
                    'Integration'
                );
            }

            // Persisted whenever there is anything worth remembering. The invoice id is kept even for
            // a failed quote: the invoice exists at Packlink either way, so the next attempt should
            // re-point that one rather than orphan it and make another.
            if ($signature !== null && ($base !== null || $invoiceId !== null)) {
                self::storeQuote(
                    $cart,
                    $signature,
                    $serviceId,
                    $base,
                    $invoiceId === null ? null : (string)$invoiceId,
                    $porterage > 0.0 ? $porterage : null,
                    isset($rows[$serviceId]) ? $rows[$serviceId] : null
                );
            }
        }

        return $bases;
    }

    /**
     * Hands the whole batch to core, or prices it one lookup at a time on a core that cannot batch.
     *
     * The module ships with its own vendored core, so the batched path may simply not be there — and a
     * fatal on a missing method would take out the whole checkout page rather than just the duties
     * option. The fallback prices each freight separately, which is slower and skips core's carrier-
     * price correction, but keeps every carrier priced from its OWN freight; degrading to one shared
     * lookup would be faster and wrong.
     *
     * @param array $items Lookups keyed by service id.
     *
     * @return array Same keys, each with 'invoiceId', 'costs' and 'error'.
     */
    private static function lookupMany(array $items)
    {
        /** @var DdpCostServiceInterface $ddpCostService */
        $ddpCostService = ServiceRegister::getService(DdpCostServiceInterface::CLASS_NAME);

        if (method_exists($ddpCostService, 'getDdpCostsMany')) {
            return $ddpCostService->getDdpCostsMany($items);
        }

        Logger::logWarning(
            'The bundled Packlink core has no batched DDP lookup, so duties are priced one service at a'
            . ' time and without the carrier-price correction. Update the core dependency.',
            'Integration'
        );

        $results = array();

        foreach ($items as $serviceId => $item) {
            $results[$serviceId] = array(
                'invoiceId' => null,
                'costs' => $ddpCostService->getDdpCosts($item['order'], $item['serviceId']),
                'error' => null,
            );
        }

        return $results;
    }

    /**
     * ISO code of the cart currency, or an empty string when it cannot be resolved.
     *
     * @param Cart $cart PrestaShop cart.
     *
     * @return string
     */
    private static function getCartCurrencyIso(Cart $cart)
    {
        $currency = new \Currency((int)$cart->id_currency);

        return \Validate::isLoadedObject($currency) ? (string)$currency->iso_code : '';
    }

    /**
     * Why a quote cannot be charged in the cart's currency, or null when it can.
     *
     * Core answers this as a code so each integration can word it in its own voice, and this maps the
     * code to the line PrestaShop logs. The refusals stay distinct all the way into the log: reported
     * as one line, a response that named no currency at all reads as an exchange-rate problem, and the
     * merchant goes looking for an FX mismatch that does not exist.
     *
     * Two of these refuse quotes this module used to charge. An enabled component naming no currency,
     * and a cart whose currency will not load, were both previously treated as "nothing to compare, so
     * assume the shop's own money". Core no longer assumes a unit it was not given, and neither does
     * this: an amount whose currency cannot be established is not money we can add to a total.
     *
     * @param DdpCostResponse|null $response Core duty cost response.
     * @param string $currencyIso ISO code of the cart currency; empty when unresolvable.
     *
     * @return string|null Reason to log, or null when the quote is usable.
     */
    private static function currencyRefusal($response, $currencyIso)
    {
        switch (DdpCostComposer::checkCurrency($response, $currencyIso)) {
            case DdpCostComposer::CURRENCY_USABLE:
                return null;
            case DdpCostComposer::CURRENCY_FOREIGN:
                return 'Packlink quoted the duty in ' . DdpCostComposer::quotedCurrency($response)
                    . ' but the cart charges in ' . $currencyIso . ', and there is no conversion here';
            case DdpCostComposer::CURRENCY_UNQUOTED:
                return 'Packlink quoted a duty amount without naming a currency, so its unit is unknown'
                    . ' and it cannot be charged';
            default:
                return 'the cart currency could not be resolved, so the quoted duty cannot be verified';
        }
    }

    /**
     * Fingerprints every input the duty quote depends on, so a persisted base is only ever reused for
     * the exact cart state it was quoted for.
     *
     * Covers destination (address id, country iso, zip — duty is route- and zip-dependent), currency,
     * declared value and the line items. Null when any input cannot be resolved: an incomplete
     * signature could match a stale row, so no signature means no reuse and no persist.
     *
     * @param Cart $cart PrestaShop cart.
     * @param array $products Non-virtual cart product rows.
     *
     * @return string|null
     */
    private static function getQuoteSignature(Cart $cart, array $products)
    {
        try {
            $address = CachingUtility::getAddress((int)$cart->id_address_delivery);
            $country = CachingUtility::getCountry((int)$address->id_country);

            $parts = array(
                (string)$cart->id,
                (string)$cart->id_address_delivery,
                strtoupper((string)$country->iso_code),
                (string)$address->postcode,
                (string)$cart->id_currency,
                // Declared value drives the duty amount; BOTH_WITHOUT_SHIPPING matches what the
                // lookup declares (shipping itself never feeds the customs value).
                (string)(float)$cart->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING),
            );

            foreach ($products as $product) {
                $parts[] = (int)$product['id_product'] . ':' . (int)$product['quantity'];
            }

            return md5(implode('|', $parts));
        } catch (\Exception $e) {
            Logger::logWarning('Failed to compute the DDP quote signature: ' . $e->getMessage(), 'Integration');

            return null;
        }
    }

    /**
     * Loads this cart's persisted quote rows, keyed by the service each was quoted for.
     *
     * All of the cart's rows in one query rather than one query per freight group: the groups are
     * walked in the pricing path, and a per-group lookup would put a query inside that loop.
     *
     * A row whose service is no longer offered for this cart is simply never read again. It is left
     * rather than pruned because the row is cart-scoped and the signature already stops a stale one
     * being served, so deleting it would spend a write to save nothing.
     *
     * @param Cart $cart PrestaShop cart.
     *
     * @return CartDdpQuote[] Rows keyed by service id; empty when there are none or storage failed.
     */
    private static function loadQuotes(Cart $cart)
    {
        try {
            $repository = RepositoryRegistry::getRepository(CartDdpQuote::getClassName());
            $filter = new QueryFilter();
            $filter->where('cartId', Operators::EQUALS, (string)$cart->id);

            $rows = array();

            /** @var CartDdpQuote $quote */
            foreach ($repository->select($filter) as $quote) {
                $rows[(string)$quote->getServiceId()] = $quote;
            }

            return $rows;
        } catch (\Exception $e) {
            Logger::logWarning('Failed to load the persisted DDP quotes: ' . $e->getMessage(), 'Integration');

            return array();
        }
    }

    /**
     * Upserts one quote row — one row per cart per service, refreshed in place when the cart changed.
     *
     * A failure here only costs the reuse (the validation request will re-quote); it must never
     * surface into pricing, hence the guard.
     *
     * A row may hold only the invoice id, with no base, when the quote itself failed. That row exists
     * to stop the next attempt orphaning the invoice, and it must never be served as a price — so its
     * signature is deliberately blanked rather than written. An empty signature cannot equal an md5,
     * so the row is unreadable as a quote by construction rather than by the reader remembering to
     * check.
     *
     * @param Cart $cart PrestaShop cart.
     * @param string $signature Fingerprint of the quoted cart state.
     * @param string|int $serviceId Service the base was quoted for.
     * @param float|null $rawBase Raw, unadjusted duty base; null when the quote failed.
     * @param string|null $invoiceId Checkout customs invoice this service now points at.
     * @param float|null $porterage Packlink's own carrier price for this service, when known.
     * @param CartDdpQuote|null $existing Row already loaded for this cart and service, when there is
     *                                    one.
     */
    private static function storeQuote(
        Cart $cart,
        $signature,
        $serviceId,
        $rawBase,
        $invoiceId,
        $porterage,
        $existing
    )
    {
        try {
            $repository = RepositoryRegistry::getRepository(CartDdpQuote::getClassName());

            $quote = $existing !== null ? $existing : new CartDdpQuote();
            $quote->setCartId((string)$cart->id);
            $quote->setSignature($rawBase === null ? '' : $signature);
            $quote->setServiceId((string)$serviceId);
            $quote->setRawBase($rawBase === null ? null : (float)$rawBase);

            // Never overwritten with nothing. A failure that produced no invoice id leaves the one
            // already on the row, which is still a real invoice at Packlink and still the one the next
            // attempt should re-point.
            if ($invoiceId !== null && $invoiceId !== '') {
                $quote->setInvoiceId($invoiceId);
            }

            // Same rule: a carrier price already learned for this service is better than nothing.
            if ($porterage !== null && (float)$porterage > 0.0) {
                $quote->setPorterage((float)$porterage);
            }

            if ($existing !== null) {
                $repository->update($quote);
            } else {
                $repository->save($quote);
            }
        } catch (\Exception $e) {
            Logger::logWarning('Failed to persist the DDP quote: ' . $e->getMessage(), 'Integration');
        }
    }

    /**
     * Returns the methods priced for this cart whose effective DDP behaviour charges duty.
     *
     * Only reachable from the pricing path (getAdjustedAmount), where CachingUtility::getCosts() has
     * already been populated with a non-empty array — so an empty calculated-costs cache means there
     * is nothing priced and therefore no eligible method.
     *
     * @return ShippingMethod[] Keyed by method id.
     */
    private static function getEligibleMethods()
    {
        $result = array();

        try {
            $calculatedCosts = CachingUtility::getCosts();
            if (!is_array($calculatedCosts) || empty($calculatedCosts)) {
                return array();
            }

            $repository = RepositoryRegistry::getRepository(ShippingMethod::getClassName());
            $filter = new QueryFilter();
            // Pricing ran this request: restrict to the methods it actually priced.
            $filter->where('id', Operators::IN, array_map('intval', array_keys($calculatedCosts)));

            /** @var ShippingMethod[] $methods */
            $methods = $repository->select($filter);

            foreach ($methods as $method) {
                if ($method->getEffectiveDdpBehavior() !== DdpBehavior::NONE) {
                    $result[$method->getId()] = $method;
                }
            }
        } catch (\Exception $e) {
            Logger::logWarning('Failed to resolve DDP-eligible methods: ' . $e->getMessage(), 'Integration');

            return array();
        }

        return $result;
    }

}
