<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Zugferd\View;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\DocumentV2\Zugferd\Calculation\NetAmount;
use Shopware\Core\Checkout\DocumentV2\Zugferd\TaxCategory;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * Precomputed XRechnung view of a single `<ram:ApplicableTradeTax>` header-tax row —
 * one entry per distinct tax rate on the order.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
final readonly class TaxBreakdownView
{
    public function __construct(
        public float $calculatedAmount,
        public float $basisAmount,
        public TaxCategory $taxCategory,
        public float $taxRate,
    ) {
    }

    /**
     * @return list<self>
     */
    public static function listFromOrder(OrderEntity $order): array
    {
        $price = $order->getPrice();

        if ($price->getTaxStatus() === CartPrice::TAX_STATE_FREE) {
            return [new self(
                calculatedAmount: 0.0,
                basisAmount: round($price->getTotalPrice(), 2),
                taxCategory: TaxCategory::ZERO_RATED,
                taxRate: 0.0,
            )];
        }

        $isGross = NetAmount::isOrderGross($order);

        $views = [];

        foreach ($price->getCalculatedTaxes() as $tax) {
            $views[] = new self(
                calculatedAmount: round($tax->getTax(), 2),
                basisAmount: round(NetAmount::fromTax($tax, null, $isGross), 2),
                taxCategory: $tax->getTaxRate() > 0.0 ? TaxCategory::STANDARD_RATE : TaxCategory::ZERO_RATED,
                taxRate: $tax->getTaxRate(),
            );
        }

        return $views;
    }
}
