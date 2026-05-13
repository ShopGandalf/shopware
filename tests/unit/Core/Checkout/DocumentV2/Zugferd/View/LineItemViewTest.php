<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Zugferd\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\DocumentV2\Zugferd\TaxCategory;
use Shopware\Core\Checkout\DocumentV2\Zugferd\UnitCode;
use Shopware\Core\Checkout\DocumentV2\Zugferd\View\LineItemView;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(LineItemView::class)]
class LineItemViewTest extends TestCase
{
    public function testListFromOrderNumbersProductLineItemsAndComputesNet(): void
    {
        $order = $this->order(CartPrice::TAX_STATE_GROSS, [
            $this->productLineItem('p-1', 'Widget', quantity: 2, total: 119.0, tax: 19.0, rate: 19.0),
        ]);

        $views = LineItemView::listFromOrder($order);

        static::assertCount(1, $views);

        $view = $views[0];
        static::assertSame('1', $view->lineId);
        static::assertSame('Widget', $view->name);
        static::assertSame(2.0, $view->quantity);
        static::assertSame(50.0, $view->netUnitPrice);
        static::assertSame(100.0, $view->lineTotal);
        static::assertSame(UnitCode::PIECE, $view->unitCode);
        static::assertSame(TaxCategory::STANDARD_RATE, $view->taxCategory);
        static::assertSame(19.0, $view->taxRate);
    }

    public function testListFromOrderKeepsNetPriceUnchangedWhenCartIsNet(): void
    {
        $order = $this->order(CartPrice::TAX_STATE_NET, [
            $this->productLineItem('p-1', 'Widget', quantity: 1, total: 100.0, tax: 19.0, rate: 19.0),
        ]);

        $view = LineItemView::listFromOrder($order)[0];

        static::assertSame(100.0, $view->lineTotal);
        static::assertSame(100.0, $view->netUnitPrice);
    }

    public function testListFromOrderUsesZeroRatedForZeroTaxRate(): void
    {
        $order = $this->order(CartPrice::TAX_STATE_NET, [
            $this->productLineItem('p-1', 'Free Sample', quantity: 1, total: 0.0, tax: 0.0, rate: 0.0),
        ]);

        $view = LineItemView::listFromOrder($order)[0];

        static::assertSame(TaxCategory::ZERO_RATED, $view->taxCategory);
        static::assertSame(0.0, $view->taxRate);
    }

    public function testListFromOrderSkipsPromotionAndCreditLineItems(): void
    {
        $order = $this->order(CartPrice::TAX_STATE_NET, [
            $this->productLineItem('p-1', 'Widget'),
            $this->lineItemOfType(LineItem::PROMOTION_LINE_ITEM_TYPE, 'PROMO'),
            $this->lineItemOfType(LineItem::CREDIT_LINE_ITEM_TYPE, 'Credit'),
        ]);

        $views = LineItemView::listFromOrder($order);

        static::assertCount(1, $views);
        static::assertSame('1', $views[0]->lineId);
    }

    public function testListFromOrderIncludesCustomLineItems(): void
    {
        $custom = $this->lineItemOfType(LineItem::CUSTOM_LINE_ITEM_TYPE, 'Custom');
        $custom->setPrice(new CalculatedPrice(
            10.0,
            10.0,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
        ));

        $order = $this->order(CartPrice::TAX_STATE_NET, [$custom]);

        $views = LineItemView::listFromOrder($order);

        static::assertCount(1, $views);
        static::assertSame('Custom', $views[0]->name);
    }

    public function testListFromOrderCarriesProductMetadata(): void
    {
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setUniqueIdentifier(Uuid::randomHex());
        $manufacturer->setName('ACME');

        $product = new ProductEntity();
        $product->setUniqueIdentifier(Uuid::randomHex());
        $product->setProductNumber('SKU-1');
        $product->setEan('1234567890123');
        $product->setPurchaseUnit(2.5);
        $product->setManufacturer($manufacturer);

        $lineItem = $this->productLineItem('p-1', 'Widget');
        $lineItem->setProduct($product);

        $view = LineItemView::listFromOrder($this->order(CartPrice::TAX_STATE_NET, [$lineItem]))[0];

        static::assertSame('SKU-1', $view->productNumber);
        static::assertSame('1234567890123', $view->ean);
        static::assertSame('ACME', $view->brandName);
        static::assertSame(2.5, $view->basisQuantity);
    }

    /**
     * @param list<OrderLineItemEntity> $lineItems
     */
    private function order(string $taxState, array $lineItems): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setPrice(new CartPrice(
            0.0,
            0.0,
            0.0,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            $taxState,
        ));
        $order->setLineItems(new OrderLineItemCollection($lineItems));

        return $order;
    }

    private function productLineItem(
        string $identifier,
        string $label,
        int $quantity = 1,
        float $total = 100.0,
        float $tax = 0.0,
        float $rate = 0.0,
    ): OrderLineItemEntity {
        $item = $this->lineItemOfType(LineItem::PRODUCT_LINE_ITEM_TYPE, $label, $identifier);
        $item->setQuantity($quantity);
        $item->setPrice(new CalculatedPrice(
            $total / max($quantity, 1),
            $total,
            new CalculatedTaxCollection([new CalculatedTax($tax, $rate, $total)]),
            new TaxRuleCollection(),
            $quantity,
        ));

        return $item;
    }

    private function lineItemOfType(string $type, string $label, string $identifier = 'item'): OrderLineItemEntity
    {
        $item = new OrderLineItemEntity();
        $item->setUniqueIdentifier(Uuid::randomHex());
        $item->setIdentifier($identifier);
        $item->setType($type);
        $item->setLabel($label);
        $item->setQuantity(1);

        return $item;
    }
}
