<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Zugferd\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Zugferd\TaxCategory;
use Shopware\Core\Checkout\DocumentV2\Zugferd\UnitCode;
use Shopware\Core\Checkout\DocumentV2\Zugferd\View\AllowanceChargeView;
use Shopware\Core\Checkout\DocumentV2\Zugferd\View\LineItemView;
use Shopware\Core\Checkout\DocumentV2\Zugferd\View\MonetarySummationView;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(MonetarySummationView::class)]
class MonetarySummationViewTest extends TestCase
{
    public function testFromOrderSumsLineAndAllowanceTotalsAndDerivesTax(): void
    {
        $order = $this->order(amountTotal: 100.0, amountNet: 84.03, currency: 'EUR');

        $lineItems = [$this->lineItem(lineTotal: 84.03)];
        $allowanceCharges = [$this->charge(2.0), $this->allowance(5.0)];

        $sum = MonetarySummationView::fromOrder($order, $lineItems, $allowanceCharges);

        static::assertSame(84.03, $sum->lineTotal);
        static::assertSame(2.0, $sum->chargeTotal);
        static::assertSame(5.0, $sum->allowanceTotal);
        static::assertSame(84.03, $sum->taxBasisTotal); // order net (authoritative)
        static::assertSame(15.97, $sum->taxTotal); // 100 - 84.03
        static::assertSame(100.0, $sum->grandTotal);
        static::assertSame('EUR', $sum->currencyCode);
        static::assertSame(3.0, $sum->rounding); // 84.03 - 84.03 - 2 + 5 → residual
    }

    public function testFromOrderTreatsOrderAsUnpaidWithoutTransaction(): void
    {
        $order = $this->order(amountTotal: 100.0, amountNet: 100.0);

        $sum = MonetarySummationView::fromOrder($order, [], []);

        static::assertSame(0.0, $sum->prepaid);
        static::assertSame(100.0, $sum->duePayable);
    }

    public function testFromOrderTreatsOrderAsPaidWhenTransactionStateMatches(): void
    {
        $order = $this->order(amountTotal: 100.0, amountNet: 100.0);
        $order->setPrimaryOrderTransaction($this->transactionWithState('paid'));

        $sum = MonetarySummationView::fromOrder($order, [], []);

        static::assertSame(100.0, $sum->prepaid);
        static::assertSame(0.0, $sum->duePayable);
    }

    public function testFromOrderDefaultsCurrencyToEur(): void
    {
        $order = $this->order(amountTotal: 0.0, amountNet: 0.0, currency: null);

        $sum = MonetarySummationView::fromOrder($order, [], []);

        static::assertSame('EUR', $sum->currencyCode);
    }

    private function order(float $amountTotal, float $amountNet, ?string $currency = null): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setAmountTotal($amountTotal);
        $order->setAmountNet($amountNet);

        if ($currency !== null) {
            $currencyEntity = new CurrencyEntity();
            $currencyEntity->setUniqueIdentifier(Uuid::randomHex());
            $currencyEntity->setIsoCode($currency);
            $order->setCurrency($currencyEntity);
        }

        return $order;
    }

    private function lineItem(float $lineTotal): LineItemView
    {
        return new LineItemView(
            lineId: '1',
            productNumber: null,
            ean: null,
            brandName: null,
            name: 'item',
            quantity: 1.0,
            basisQuantity: 1.0,
            unitCode: UnitCode::PIECE,
            netUnitPrice: $lineTotal,
            lineTotal: $lineTotal,
            taxCategory: TaxCategory::STANDARD_RATE,
            taxRate: 19.0,
        );
    }

    private function charge(float $amount): AllowanceChargeView
    {
        return new AllowanceChargeView(
            isCharge: true,
            actualAmount: $amount,
            basisAmount: null,
            calculationPercent: null,
            reasonCode: AllowanceChargeView::REASON_CODE_DELIVERY,
            reason: 'Delivery',
            taxCategory: TaxCategory::STANDARD_RATE,
            taxRate: 19.0,
        );
    }

    private function allowance(float $amount): AllowanceChargeView
    {
        return new AllowanceChargeView(
            isCharge: false,
            actualAmount: $amount,
            basisAmount: null,
            calculationPercent: null,
            reasonCode: AllowanceChargeView::REASON_CODE_DISCOUNT,
            reason: 'PROMO',
            taxCategory: TaxCategory::STANDARD_RATE,
            taxRate: 19.0,
        );
    }

    private function transactionWithState(string $technicalName): OrderTransactionEntity
    {
        $state = new StateMachineStateEntity();
        $state->setUniqueIdentifier(Uuid::randomHex());
        $state->setTechnicalName($technicalName);

        $transaction = new OrderTransactionEntity();
        $transaction->setUniqueIdentifier(Uuid::randomHex());
        $transaction->setStateMachineState($state);

        return $transaction;
    }
}
