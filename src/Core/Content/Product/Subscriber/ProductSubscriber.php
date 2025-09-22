<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Subscriber;

use Shopware\Core\Content\MeasurementSystem\MeasurementUnits;
use Shopware\Core\Content\MeasurementSystem\MeasurementUnitTypeEnum;
use Shopware\Core\Content\MeasurementSystem\ProductMeasurement\ProductMeasurementEnum;
use Shopware\Core\Content\MeasurementSystem\ProductMeasurement\ProductMeasurementUnitBuilder;
use Shopware\Core\Content\MeasurementSystem\Unit\AbstractMeasurementUnitConverter;
use Shopware\Core\Content\Product\AbstractIsNewDetector;
use Shopware\Core\Content\Product\AbstractProductMaxPurchaseCalculator;
use Shopware\Core\Content\Product\AbstractProductVariationBuilder;
use Shopware\Core\Content\Product\AbstractPropertyGroupSorter;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPrice;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\SalesChannel\Price\AbstractProductPriceCalculator;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('inventory')]
class ProductSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     *
     * @param SalesChannelRepository<SalesChannelProductEntity> $productRepository
     */
    public function __construct(
        private readonly AbstractProductVariationBuilder $productVariationBuilder,
        private readonly AbstractProductPriceCalculator $calculator,
        private readonly AbstractPropertyGroupSorter $propertyGroupSorter,
        private readonly AbstractProductMaxPurchaseCalculator $maxPurchaseCalculator,
        private readonly AbstractIsNewDetector $isNewDetector,
        private readonly SystemConfigService $systemConfigService,
        private readonly ProductMeasurementUnitBuilder $measurementUnitBuilder,
        private readonly AbstractMeasurementUnitConverter $measurementUnitConverter,
        private readonly RequestStack $requestStack,
        private readonly SalesChannelRepository $productRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_LOADED_EVENT => 'loaded',
            'product.partial_loaded' => 'loaded',
            'sales_channel.' . ProductEvents::PRODUCT_LOADED_EVENT => 'salesChannelLoaded',
            'sales_channel.product.partial_loaded' => 'salesChannelLoaded',
            EntityWriteEvent::class => 'beforeWriteProduct',
        ];
    }

    /**
     * @param EntityLoadedEvent<ProductEntity|PartialEntity> $event
     */
    public function loaded(EntityLoadedEvent $event): void
    {
        $isAdminSource = $event->getContext()->getSource() instanceof AdminApiSource;

        foreach ($event->getEntities() as $product) {
            if (!$product instanceof ProductEntity && !$product instanceof PartialEntity) {
                continue;
            }

            if ($isAdminSource) {
                $this->convertMeasurementUnit($product);
            }

            $this->setDefaultLayout($product);

            $this->productVariationBuilder->build($product);
        }
    }

    /**
     * @param SalesChannelEntityLoadedEvent<ProductEntity|PartialEntity> $event
     */
    public function salesChannelLoaded(SalesChannelEntityLoadedEvent $event): void
    {
        $parentProducts = [];

        foreach ($event->getEntities() as $product) {
            $price = $product->get('cheapestPrice');

            // Check if this product needs dynamic cheapest price calculation
            $needsDynamicCalculation = ($product->get('childCount') > 0 && $product->get('parentId') === null) || 
                                     ($product->get('parentId') !== null);

            if ($price instanceof CheapestPriceContainer && !$needsDynamicCalculation) {
                // Only resolve from container for single products that don't need dynamic calculation
                $resolvedPrice = $price->resolve($event->getSalesChannelContext()->getContext());
                $product->assign([
                    'cheapestPrice' => $resolvedPrice,
                    'cheapestPriceContainer' => $price,
                ]);
            } elseif ($price instanceof CheapestPriceContainer) {
                // For products that need dynamic calculation, just store the container
                $product->assign(['cheapestPriceContainer' => $price]);
            }

            // Collect products that need dynamic cheapest price calculation
            if ($needsDynamicCalculation) {
                $parentProducts[] = $product;
            }

            $assigns = [];

            if (($properties = $product->get('properties')) !== null) {
                $assigns['sortedProperties'] = $this->propertyGroupSorter->sort($properties);
            }

            $assigns['calculatedMaxPurchase'] = $this->maxPurchaseCalculator->calculate($product, $event->getSalesChannelContext());

            $assigns['isNew'] = $this->isNewDetector->isNew($product, $event->getSalesChannelContext());

            $assigns['measurements'] = $this->measurementUnitBuilder->buildFromContext($product, $event->getSalesChannelContext());

            $product->assign($assigns);

            $this->setDefaultLayout($product, $event->getSalesChannelContext()->getSalesChannelId());

            $this->productVariationBuilder->build($product);
        }

        // Calculate dynamic cheapest prices for parent products
        if (!empty($parentProducts)) {
            $this->calculateDynamicCheapestPrices($parentProducts, $event->getSalesChannelContext());
        }

        $this->calculator->calculate($event->getEntities(), $event->getSalesChannelContext());
    }

    public function beforeWriteProduct(EntityWriteEvent $event): void
    {
        $lengthUnitHeader = $this->requestStack->getCurrentRequest()?->headers->get(PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT);
        $weightUnitHeader = $this->requestStack->getCurrentRequest()?->headers->get(PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT);

        if (!$lengthUnitHeader && !$weightUnitHeader) {
            return;
        }

        $commands = $event->getCommandsForEntity(ProductDefinition::ENTITY_NAME);

        foreach ($commands as $command) {
            $payload = $command->getPayload();

            foreach (ProductMeasurementEnum::DIMENSIONS_MAPPING as $dimension => $type) {
                if (!$command->hasField($dimension) || !\is_float($payload[$dimension] ?? null)) {
                    continue;
                }

                $fromUnit = $type === MeasurementUnitTypeEnum::WEIGHT
                    ? $weightUnitHeader
                    : $lengthUnitHeader;

                $toUnit = $type === MeasurementUnitTypeEnum::WEIGHT
                    ? MeasurementUnits::DEFAULT_WEIGHT_UNIT
                    : MeasurementUnits::DEFAULT_LENGTH_UNIT;

                if ($fromUnit) {
                    $command->addPayload($dimension, $this->measurementUnitConverter->convert(
                        $payload[$dimension],
                        $fromUnit,
                        $toUnit,
                    )->value);
                }
            }
        }
    }

    /**
     * @param Entity $product - typehint as Entity because it could be a ProductEntity or PartialEntity
     */
    private function setDefaultLayout(Entity $product, ?string $salesChannelId = null): void
    {
        if (!$product->has('cmsPageId')) {
            return;
        }

        if ($product->get('cmsPageId') !== null) {
            return;
        }

        $cmsPageId = $this->systemConfigService->get(ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT, $salesChannelId);

        if (!$cmsPageId) {
            return;
        }

        $product->assign(['cmsPageId' => $cmsPageId]);
    }

    /**
     * @param array<Entity> $products
     */
    private function calculateDynamicCheapestPrices(array $products, SalesChannelContext $context): void
    {
        $processedParents = [];

        foreach ($products as $product) {
            $parentId = $product->get('parentId');

            // Determine the parent ID
            if ($parentId === null) {
                // This is a parent product
                $parentId = $product->getParentId();
            }

            // Skip if we already processed this parent
            if (isset($processedParents[$parentId])) {
                continue;
            }

            $variants = $this->loadVariantsForParent($parentId, $context);

            if (empty($variants)) {
                continue;
            }

            $cheapestPrice = $this->findCheapestPriceFromVariants($variants, $context);

            if ($cheapestPrice !== null) {
                // Assign the cheapest price to all products that belong to this parent
                foreach ($products as $productToUpdate) {
                    $productParentId = $productToUpdate->get('parentId');
                    if ($productParentId === null) {
                        $productParentId = $productToUpdate->getId();
                    }

                    if ($productParentId === $parentId) {
                        $productToUpdate->assign(['cheapestPrice' => $cheapestPrice]);
                    }
                }
            }

            $processedParents[$parentId] = true;
        }
    }

    /**
     * @return array<SalesChannelProductEntity>
     */
    private function loadVariantsForParent(string $parentId, SalesChannelContext $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', $parentId));
        $criteria->addFilter(new EqualsFilter('active', true));

        // Add sales channel visibility filter
        $criteria->addFilter(new EqualsFilter('visibilities.salesChannelId', $context->getSalesChannelId()));
        $criteria->addFilter(new EqualsFilter('visibilities.visibility', ProductVisibilityDefinition::VISIBILITY_LINK));

        // Add associations for price calculation
        $criteria->addAssociation('prices');
        $criteria->addAssociation('tax');
        $criteria->addAssociation('unit');

        $criteria->setTitle('product-subscriber::variants-for-cheapest-price');

        return $this->productRepository->search($criteria, $context)->getEntities()->getElements();
    }

    /**
     * @param array<SalesChannelProductEntity> $variants
     */
    private function findCheapestPriceFromVariants(array $variants, SalesChannelContext $context): ?CheapestPrice
    {
        $cheapestVariant = null;
        $cheapestPrice = null;

        foreach ($variants as $variant) {
            $price = $variant->get('price');
            if ($price === null) {
                continue;
            }

            // Get the price for the current currency and tax state
            $currencyPrice = $price->getCurrencyPrice($context->getCurrencyId());
            if ($currencyPrice === null) {
                continue;
            }

            $variantPrice = $context->getTaxState() === 'gross'
                ? $currencyPrice->getGross()
                : $currencyPrice->getNet();

            if ($cheapestPrice === null || $variantPrice < $cheapestPrice) {
                $cheapestPrice = $variantPrice;
                $cheapestVariant = $variant;
            }
        }

        if ($cheapestVariant === null) {
            return null;
        }

        // Create a CheapestPrice object from the cheapest variant
        $cheapestPriceObj = new CheapestPrice();
        $cheapestPriceObj->setVariantId($cheapestVariant->getId());
        $cheapestPriceObj->setParentId($cheapestVariant->getParentId());
        $cheapestPriceObj->setHasRange(count($variants) > 1);

        // Set the price collection from the cheapest variant
        $cheapestPriceObj->setPrice($cheapestVariant->get('price'));

        return $cheapestPriceObj;
    }


    private function convertMeasurementUnit(ProductEntity|PartialEntity $product): void
    {
        $lengthUnitHeader = $this->requestStack->getCurrentRequest()?->headers->get(PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT);
        $weightUnitHeader = $this->requestStack->getCurrentRequest()?->headers->get(PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT);

        if (!$lengthUnitHeader && !$weightUnitHeader) {
            return;
        }

        $toLengthUnit = $lengthUnitHeader ?? MeasurementUnits::DEFAULT_LENGTH_UNIT;
        $toWeightUnit = $weightUnitHeader ?? MeasurementUnits::DEFAULT_WEIGHT_UNIT;

        $converted = $this->measurementUnitBuilder->build($product, $toLengthUnit, $toWeightUnit);

        $assigns = [];

        foreach ($converted->getUnits() as $unit => $convertedUnit) {
            $assigns[$unit] = $convertedUnit->value;
        }

        if (!empty($assigns)) {
            $product->assign($assigns);
        }
    }
}
