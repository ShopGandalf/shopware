<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Zugferd\View;

use Shopware\Core\Checkout\DocumentV2\Zugferd\Calculation\NetAmount;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * Precomputed XRechnung view of `<ram:SpecifiedTradeSettlementHeaderMonetarySummation>`.
 *
 * Sources the 9 wire fields from values already computed elsewhere:
 *  - `lineTotal` / `chargeTotal` / `allowanceTotal` — summed from the existing
 *    {@see LineItemView} and {@see AllowanceChargeView} buckets (both are precomputed via
 *    {@see NetAmount}, which ports
 *    v1's tax math verbatim).
 *  - `grandTotal` / `taxTotal` / `taxBasisTotal` — read straight off the order.
 *  - `prepaid` / `duePayable` — derived from the primary transaction's state.
 *
 * No re-aggregation, no `AmountCalculator` round-trip — the math has already been done.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
final readonly class MonetarySummationView
{
    public function __construct(
        public float $lineTotal,
        public float $chargeTotal,
        public float $allowanceTotal,
        public float $taxBasisTotal,
        public float $taxTotal,
        public string $currencyCode,
        public float $rounding,
        public float $grandTotal,
        public float $prepaid,
        public float $duePayable,
    ) {
    }

    /**
     * @param list<LineItemView> $lineItems
     * @param list<AllowanceChargeView> $allowanceCharges
     */
    public static function fromOrder(OrderEntity $order, array $lineItems, array $allowanceCharges): self
    {
        $lineTotal = array_sum(array_map(
            static fn (LineItemView $item): float => $item->lineTotal,
            $lineItems,
        ));

        $chargeTotal = array_sum(array_map(
            static fn (AllowanceChargeView $ac): float => $ac->isCharge ? $ac->actualAmount : 0.0,
            $allowanceCharges,
        ));

        $allowanceTotal = array_sum(array_map(
            static fn (AllowanceChargeView $ac): float => $ac->isCharge ? 0.0 : $ac->actualAmount,
            $allowanceCharges,
        ));

        $grand = $order->getAmountTotal();
        $net = $order->getAmountNet();
        $paid = self::resolvePaidAmount($order);

        return new self(
            lineTotal: round($lineTotal, 2),
            chargeTotal: round($chargeTotal, 2),
            allowanceTotal: round($allowanceTotal, 2),
            taxBasisTotal: round($net - $lineTotal - $chargeTotal + $allowanceTotal, 2),
            taxTotal: round($grand - $net, 2),
            currencyCode: $order->getCurrency()?->getIsoCode() ?? 'EUR',
            rounding: 0.0,
            grandTotal: round($grand, 2),
            prepaid: round($paid, 2),
            duePayable: round($grand - $paid, 2),
        );
    }

    private static function resolvePaidAmount(OrderEntity $order): float
    {
        $transaction = $order->getPrimaryOrderTransaction()
            ?? $order->getTransactions()?->last();

        return $transaction?->getStateMachineState()?->getTechnicalName() === 'paid'
            ? $order->getAmountTotal()
            : 0.0;
    }
}
