<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Zugferd\View;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\DocumentV2\Zugferd\TaxCategory;
use Shopware\Core\Checkout\DocumentV2\Zugferd\UnitCode;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * Precomputed XRechnung view of a single billable line item.
 *
 * Shared across document types (invoice, delivery note, credit note, …) that render order
 * positions as `<ram:IncludedSupplyChainTradeLineItem>`.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
final readonly class LineItemView
{
    public function __construct(
        public string $lineId,
        public ?string $productNumber,
        public ?string $ean,
        public ?string $brandName,
        public string $name,
        public float $quantity,
        public float $basisQuantity,
        public UnitCode $unitCode,
        public float $netUnitPrice,
        public float $lineTotal,
        public TaxCategory $taxCategory,
        public float $taxRate,
    ) {
    }

    /**
     * @return list<self>
     */
    public static function listFromOrder(OrderEntity $order): array
    {
        $isGross = !$order->getPrice()->hasNetPrices();

        $items = [];
        $position = 0;

        foreach ($order->getLineItems() ?? [] as $lineItem) {
            if (!\in_array(
                $lineItem->getType(),
                [LineItem::PRODUCT_LINE_ITEM_TYPE, LineItem::CUSTOM_LINE_ITEM_TYPE],
                true,
            )) {
                continue;
            }

            $price = $lineItem->getPrice();

            if ($price === null) {
                continue;
            }

            $totalNet = $isGross
                ? $price->getTotalPrice() - $price->getCalculatedTaxes()->getAmount()
                : $price->getTotalPrice();

            $quantity = max($lineItem->getQuantity(), 1);
            $taxRate = $price->getCalculatedTaxes()->first()?->getTaxRate() ?? 0.0;
            $product = $lineItem->getProduct();

            $items[] = new self(
                lineId: (string) ++$position,
                productNumber: $product?->getProductNumber(),
                ean: $product?->getEan(),
                brandName: $product?->getManufacturer()?->getName(),
                name: $lineItem->getLabel(),
                quantity: $quantity,
                basisQuantity: (float) ($product?->getPurchaseUnit() ?? 1),
                unitCode: UnitCode::PIECE,
                netUnitPrice: round($totalNet / $quantity, 2),
                lineTotal: round($totalNet, 2),
                taxCategory: $taxRate > 0.0 ? TaxCategory::STANDARD_RATE : TaxCategory::ZERO_RATED,
                taxRate: $taxRate,
            );
        }

        return $items;
    }
}
