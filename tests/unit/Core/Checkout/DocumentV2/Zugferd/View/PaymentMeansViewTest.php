<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Zugferd\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Zugferd\PaymentMeansCode;
use Shopware\Core\Checkout\DocumentV2\Zugferd\View\PaymentMeansView;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(PaymentMeansView::class)]
class PaymentMeansViewTest extends TestCase
{
    public static function provideHandlerMappings(): iterable
    {
        yield 'cash on delivery → CASH' => [
            'payment_cashpayment',
            PaymentMeansCode::CASH,
            false,
        ];

        yield 'invoice → CREDIT_TRANSFER' => [
            'payment_invoicepayment',
            PaymentMeansCode::CREDIT_TRANSFER,
            true,
        ];

        yield 'prepayment → CREDIT_TRANSFER' => [
            'payment_prepayment',
            PaymentMeansCode::CREDIT_TRANSFER,
            true,
        ];

        yield 'debit → SEPA_DIRECT_DEBIT' => [
            'payment_debitpayment',
            PaymentMeansCode::SEPA_DIRECT_DEBIT,
            true,
        ];
    }

    #[DataProvider('provideHandlerMappings')]
    public function testFromOrderMapsHandlerToCode(string $technicalName, PaymentMeansCode $expected, bool $expectsBankDetails): void
    {
        $order = $this->orderWithTransaction($technicalName, 'Friendly Label');

        $view = PaymentMeansView::fromOrder($order, 'DE89370400440532013000', 'COBADEFFXXX');

        static::assertNotNull($view);
        static::assertSame($expected, $view->typeCode);
        static::assertSame('Friendly Label', $view->information);

        if ($expectsBankDetails) {
            static::assertSame('DE89370400440532013000', $view->payeeIban);
            static::assertSame('COBADEFFXXX', $view->payeeBic);
        } else {
            static::assertNull($view->payeeIban);
            static::assertNull($view->payeeBic);
        }
    }

    public function testFromOrderReturnsNullForUnknownHandler(): void
    {
        $order = $this->orderWithTransaction('payment_defaultpayment', 'Default');

        static::assertNull(PaymentMeansView::fromOrder($order, null, null));
    }

    public function testFromOrderReturnsNullWhenNoTransaction(): void
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        static::assertNull(PaymentMeansView::fromOrder($order, null, null));
    }

    private function orderWithTransaction(string $technicalName, string $label): OrderEntity
    {
        $method = new PaymentMethodEntity();
        $method->setUniqueIdentifier(Uuid::randomHex());
        $method->setTechnicalName($technicalName);
        $method->setName($label);

        $transaction = new OrderTransactionEntity();
        $transaction->setUniqueIdentifier(Uuid::randomHex());
        $transaction->setPaymentMethod($method);

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setPrimaryOrderTransaction($transaction);
        $order->setTransactions(new OrderTransactionCollection([$transaction]));

        return $order;
    }
}
