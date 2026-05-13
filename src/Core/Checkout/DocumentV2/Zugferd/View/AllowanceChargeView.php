<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Zugferd\View;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\DocumentV2\Zugferd\Calculation\NetAmount;
use Shopware\Core\Checkout\DocumentV2\Zugferd\TaxCategory;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * Precomputed XRechnung view of a single `<ram:SpecifiedTradeAllowanceCharge>` entry —
 * one per (promotion/credit line item × tax breakdown row).
 *
 * Positive `isCharge` flips the wire `<udt:Indicator>` to `true`; everything else (allowance) flips it to `false`.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
final readonly class AllowanceChargeView
{
    public const REASON_CODE_DISCOUNT = 'DISCOUNT';

    public function __construct(
        public bool $isCharge,
        public float $actualAmount,
        public ?float $basisAmount,
        public ?float $calculationPercent,
        public string $reasonCode,
        public string $reason,
        public TaxCategory $taxCategory,
        public float $taxRate,
    ) {
    }

    /**
     * @return list<self>
     */
    public static function listFromOrder(OrderEntity $order): array
    {
        $isGross = NetAmount::isOrderGross($order);

        $views = [];

        foreach ($order->getLineItems() ?? [] as $lineItem) {
            if (!\in_array(
                $lineItem->getType(),
                [LineItem::PROMOTION_LINE_ITEM_TYPE, LineItem::CREDIT_LINE_ITEM_TYPE],
                true,
            )) {
                continue;
            }

            $price = $lineItem->getPrice();

            if ($price === null) {
                continue;
            }

            $payload = $lineItem->getPayload();

            $discountValue = (float) ($payload['value'] ?? 0);
            $maxValue = (float) ($payload['maxValue'] ?? 0);

            $isPercentage = ($payload['discountType'] ?? null) === PromotionDiscountEntity::TYPE_PERCENTAGE
                && abs($price->getTotalPrice()) !== $maxValue;

            $isCharge = $price->getUnitPrice() >= 0;

            foreach ($price->getCalculatedTaxes() as $tax) {
                $actualAmount = NetAmount::fromTax($tax, $price, $isGross);
                $absAmount = round(abs($actualAmount), 2);

                $views[] = new self(
                    isCharge: $isCharge,
                    actualAmount: $absAmount,
                    basisAmount: $isPercentage && $discountValue !== 0.0
                        ? round($absAmount * 100 / $discountValue, 2)
                        : null,
                    calculationPercent: $isPercentage ? $discountValue : null,
                    reasonCode: self::REASON_CODE_DISCOUNT,
                    reason: $lineItem->getReferencedId() ?? $lineItem->getLabel(),
                    taxCategory: $tax->getTaxRate() > 0.0 ? TaxCategory::STANDARD_RATE : TaxCategory::ZERO_RATED,
                    taxRate: $tax->getTaxRate(),
                );
            }
        }

        return $views;
    }
}
