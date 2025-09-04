<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Generator;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\DataAbstractionLayer\StatesUpdater;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataException;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Demodata\DemodataService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxEntity;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('inventory')]
class ProductGenerator implements DemodataGeneratorInterface
{
    private SymfonyStyle $io;

    private Generator $faker;

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly DefinitionInstanceRegistry $registry,
        private readonly InheritanceUpdater $updater,
        private readonly StatesUpdater $statesUpdater
    ) {
    }

    public function getDefinition(): string
    {
        return ProductDefinition::class;
    }

    /**
     * @param mixed[] $options
     */
    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $this->faker = $context->getFaker();
        $this->io = $context->getConsole();

        $this->createProducts($context->getContext(), $numberOfItems);
    }

    private function createProducts(Context $context, int $count): void
    {
        $visibilities = $this->buildVisibilities();

        $taxes = $this->getTaxes($context);

        if ($taxes->count() === 0) {
            throw DemodataException::wrongExecutionOrder();
        }

        $properties = $this->getProperties();

        $this->io->progressStart($count);

        $mediaIds = $this->getMediaIds();
        $downloadMediaIds = $this->getMediaIds('product_download');

        $ruleIds = $this->getIds('rule');

        $manufacturers = $this->getIds('product_manufacturer');

        $tags = $this->getIds('tag');

        $instantDeliveryId = $this->getInstantDeliveryId();

        $combinations = [];
        for ($i = 0; $i <= 20; ++$i) {
            $combinations[] = $this->buildCombinations($properties);
        }

        $max = max(min($count / 3, 200), 5);
        $prices = [];
        for ($i = 0; $i <= $max; ++$i) {
            $prices[] = $this->createPrices($ruleIds);
        }

        $payload = [];
        for ($i = 0; $i < $count; ++$i) {
            $product = $this->createSimpleProduct($taxes, $manufacturers, $tags);

            $product['prices'] = $this->faker->randomElement($prices);

            $product['visibilities'] = $visibilities;

            if ($mediaIds) {
                $product['cover'] = ['mediaId' => Random::getRandomArrayElement($mediaIds)];

                $product['media'] = array_map(fn (string $id): array => ['mediaId' => $id], $this->faker->randomElements($mediaIds, random_int(2, 5)));
            }

            $product['properties'] = $this->buildProperties($properties);

            if ($i % 40 === 0) {
                $combination = $this->faker->randomElement($combinations);
                $product = [...$product, ...$this->buildVariants($combination, $prices, $taxes)];
            } elseif ($i % 20 === 0) {
                $product = [...$product, ...$this->buildDownloads($downloadMediaIds, $instantDeliveryId)];
            }

            $payload[] = $product;

            if (\count($payload) >= 20) {
                $this->io->progressAdvance(\count($payload));
                $this->write($payload, $context);
                $payload = [];
            }
        }

        // Add products with static data.
        array_push($payload, ...$this->createStaticProducts($taxes, $manufacturers, $visibilities, $mediaIds));

        if (!empty($payload)) {
            $this->write($payload, $context);
        }

        $this->io->progressFinish();
    }

    /**
     * @param array<string, list<string>> $properties
     *
     * @return array<array<string, mixed>>
     */
    private function buildCombinations(array $properties): array
    {
        $properties = $this->faker->randomElements($properties, random_int(min(\count($properties), 1), min(\count($properties), 4)));

        $mapped = [];
        // reduce permutation count
        foreach ($properties as $index => $values) {
            $permutations = is_countable($values) ? \count($values) : 0;
            if ($permutations > 4) {
                $permutations = random_int(2, 4);
            }
            $mapped[$index] = $this->faker->randomElements($values, $permutations);
        }
        $properties = $mapped;

        $result = [[]];
        foreach ($properties as $property => $property_values) {
            $tmp = [];
            foreach ($result as $result_item) {
                foreach ($property_values as $property_value) {
                    $tmp[] = array_merge($result_item, [$property => $property_value]);
                }
            }
            $result = $tmp;
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $combinations
     * @param list<list<array<string, mixed>>> $prices
     *
     * @return array<string, mixed>
     */
    private function buildVariants(array $combinations, array $prices, TaxCollection $taxes): array
    {
        $configurator = [];

        $variants = [];
        foreach ($combinations as $options) {
            $price = $this->faker->randomFloat(2, 1, 1000);
            $tax = $taxes->get(array_rand($taxes->getIds()));
            if (!$tax instanceof TaxEntity) {
                continue;
            }
            $taxRate = 1 + ($tax->getTaxRate() / 100);

            $id = Uuid::randomHex();
            $variants[] = [
                'id' => $id,
                'productNumber' => 'SW_' . $id,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => $price, 'net' => $price / $taxRate, 'linked' => true]],
                'active' => true,
                'stock' => $this->faker->numberBetween(1, 50),
                'prices' => $this->faker->randomElement($prices),
                'options' => array_map(fn ($id) => ['id' => $id], $options),
            ];

            $configurator = [...$configurator, ...array_values($options)];
        }

        return [
            'children' => $variants,
            'configuratorSettings' => array_map(fn (string $id) => ['optionId' => $id], array_filter(array_unique($configurator))),
        ];
    }

    /**
     * @param list<string>|list<array<string, string>> $downloadMediaIds
     *
     * @return array{downloads: list<array{id: string, mediaId: string, position: int}>, maxPurchase: 1, deliveryTimeId: string|null}
     */
    private function buildDownloads(array $downloadMediaIds, ?string $instantDeliveryId): array
    {
        $mediaIds = $this->faker->randomElements($downloadMediaIds, random_int(1, 3), false);

        $downloads = [];
        foreach ($mediaIds as $position => $mediaId) {
            $downloads[] = [
                'id' => Uuid::randomHex(),
                'mediaId' => $mediaId,
                'position' => $position,
            ];
        }

        return [
            'downloads' => $downloads,
            'maxPurchase' => 1,
            'deliveryTimeId' => $instantDeliveryId,
        ];
    }

    /**
     * @param list<array<string, mixed>> $payload
     */
    private function write(array $payload, Context $context): void
    {
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $this->registry->getRepository('product')->create($payload, $context);

        $all = array_column($payload, 'id');
        foreach ($payload as $product) {
            if (!isset($product['children'])) {
                continue;
            }
            $all = [...$all, ...array_column($product['children'], 'id')];
        }

        $this->updater->update(ProductDefinition::ENTITY_NAME, $all, $context);
        $this->statesUpdater->update($all, $context);

        $context->removeState(EntityIndexerRegistry::DISABLE_INDEXING);
    }

    private function getTaxes(Context $context): TaxCollection
    {
        $taxRepository = $this->registry->getRepository('tax');

        $taxCollection = $taxRepository->search(new Criteria(), $context)->getEntities();
        \assert($taxCollection instanceof TaxCollection);

        return $taxCollection;
    }

    /**
     * @param array<string> $manufacturer
     * @param array<string> $tags
     *
     * @return array<string, mixed>
     */
    private function createSimpleProduct(
        TaxCollection $taxes,
        array $manufacturer,
        array $tags
    ): array {
        $price = $this->faker->randomFloat(2, 1, 1000);
        $purchasePrice = $this->faker->randomFloat(2, 1, 1000);
        $tax = $taxes->get(array_rand($taxes->getIds()));
        \assert($tax instanceof TaxEntity);
        $taxRate = 1 + ($tax->getTaxRate() / 100);

        return [
            'id' => Uuid::randomHex(),
            'productNumber' => 'SW_' . Uuid::randomHex(),
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => $price, 'net' => $price / $taxRate, 'linked' => true]],
            'purchasePrices' => [['currencyId' => Defaults::CURRENCY, 'gross' => $purchasePrice, 'net' => $purchasePrice / $taxRate, 'linked' => true]],
            'name' => $this->faker->format('productName'),
            'description' => $this->faker->text(),
            'taxId' => $tax->getId(),
            'manufacturerId' => $this->faker->randomElement($manufacturer),
            'active' => true,
            'height' => $this->faker->numberBetween(1, 1000),
            'width' => $this->faker->numberBetween(1, 1000),
            'categories' => $this->getCategoryIds(),
            'tags' => $this->getTags($tags),
            'stock' => $this->faker->numberBetween(1, 50),
            'customFields' => [DemodataService::DEMODATA_CUSTOM_FIELDS_KEY => true],
        ];
    }

    /**
     * @param array<string> $rules
     *
     * @return list<array<string, mixed>>
     */
    private function createPrices(array $rules): array
    {
        $prices = [];
        $rules = \array_slice(
            $rules,
            random_int(0, \max(\count($rules) - 3, 1)),
            random_int(1, 3)
        );

        $values = [];
        for ($i = 1; $i <= 200; ++$i) {
            $value = $this->faker->randomFloat(2, $i * 10, $i * 100);

            $values[] = [
                $value,
                round($value / 100 * random_int(50, 90), 2),
            ];
        }

        foreach ($rules as $ruleId) {
            $price = $this->faker->randomElement($values);

            $prices[] = [
                'ruleId' => $ruleId,
                'quantityStart' => 1,
                'quantityEnd' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => $price[0], 'net' => $price[0] / 119, 'linked' => false]],
            ];

            $prices[] = [
                'ruleId' => $ruleId,
                'quantityStart' => 11,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => $price[1], 'net' => $price[1] / 119, 'linked' => false]],
            ];
        }

        return $prices;
    }

    /**
     * @param array<string> $tags
     *
     * @return array<array{id: string}>
     */
    private function getTags(array $tags): array
    {
        $tagAssignments = [];

        if (!empty($tags)) {
            $chosenTags = $this->faker->randomElements($tags, $this->faker->randomDigit(), false);

            if (!empty($chosenTags)) {
                $tagAssignments = array_map(
                    fn ($id) => ['id' => $id],
                    $chosenTags
                );
            }
        }

        return $tagAssignments;
    }

    /**
     * @return array<string, list<string>>
     */
    private function getProperties(): array
    {
        $options = $this->connection->fetchAllAssociative('SELECT LOWER(HEX(id)) as id, LOWER(HEX(property_group_id)) as property_group_id FROM property_group_option LIMIT 5000');

        $grouped = [];
        foreach ($options as $option) {
            $grouped[(string) $option['property_group_id']][] = (string) $option['id'];
        }

        return $grouped;
    }

    /**
     * @return array<array{salesChannelId: string, visibility: ProductVisibilityDefinition::VISIBILITY_ALL}>
     */
    private function buildVisibilities(): array
    {
        $ids = $this->connection->fetchAllAssociative('SELECT LOWER(HEX(id)) as id FROM sales_channel LIMIT 100');

        return array_map(fn ($id) => ['salesChannelId' => $id['id'], 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL], $ids);
    }

    /**
     * @return list<string>|list<array<string, string>>
     */
    private function getMediaIds(string $entity = 'product'): array
    {
        $repository = $this->registry->getRepository('media');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mediaFolder.defaultFolder.entity', $entity));
        $criteria->setLimit(500);

        return $repository->searchIds($criteria, Context::createDefaultContext())->getIds();
    }

    /**
     * @return list<string>
     */
    private function getIds(string $table): array
    {
        return $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) as id FROM ' . $table . ' LIMIT 500');
    }

    /**
     * @return list<array{id: string}>
     */
    private function getCategoryIds(): array
    {
        /** @var list<array{id: string}> $result */
        $result = $this->connection->fetchAllAssociative('
            SELECT LOWER(HEX(category.id)) as id
            FROM category
             LEFT JOIN product_category pc
               ON pc.category_id = category.id
            WHERE category.child_count = 0
            GROUP BY category.id
            ORDER BY COUNT(pc.product_id) ASC
            LIMIT ' . $this->faker->numberBetween(1, 3));

        return $result;
    }

    /**
     * @param array<string, list<string>> $properties
     *
     * @return array<array{id: string}>
     */
    private function buildProperties(array $properties): array
    {
        $productProperties = [];
        foreach ($properties as $options) {
            $productProperties = array_merge($productProperties, $this->faker->randomElements($options, min(\count($options), 3)));
        }

        $productProperties = \array_slice($productProperties, 0, random_int(4, 10));

        return array_map(fn ($config) => ['id' => (string) $config], $productProperties);
    }

    private function getInstantDeliveryId(): ?string
    {
        $id = $this->connection->fetchOne('SELECT LOWER(HEX(delivery_time_id)) FROM delivery_time_translation WHERE `name` = "Instant download" LIMIT 1');

        return \is_string($id) ? $id : null;
    }

    private function createStaticProducts($taxes, $manufacturers, $visibilities, $mediaIds): array {
        $staticProducts = [];

        $staticProducts[] = $this->createStaticProductSimplePrice($taxes, $manufacturers, $visibilities, $mediaIds);
        $staticProducts[] = $this->createStaticProductManyReviews($taxes, $manufacturers, $visibilities, $mediaIds);
        $staticProducts[] = $this->createStaticProductWithListPrice($taxes, $manufacturers, $visibilities, $mediaIds);
        $staticProducts[] = $this->createStaticProductLongDescription($taxes, $manufacturers, $visibilities, $mediaIds);
        // Product with list price
        // Product with many media items
        // Product with extensive description
        // Media video
        // Free shipping
        // New date

        return $staticProducts;
    }

    private function getCategoryByName(string $name): ?array
    {
        $result = $this->connection->fetchAllAssociative('
            SELECT LOWER(HEX(category.id)) as id
            FROM category
            INNER JOIN category_translation 
                ON category_translation.category_id = category.id
            WHERE category_translation.name = :name
            LIMIT 1',
            ['name' => $name]
        );

        return $result ?: null;
    }

    /**
     * @param array<string> $manufacturer
     *
     * @return array<string, mixed>
     */
    private function createStaticProductSimplePrice(TaxCollection $taxes, array $manufacturer, array $visibilities, array $mediaIds): array
    {
        $tax = $taxes->get(array_rand($taxes->getIds()));
        \assert($tax instanceof TaxEntity);
        $taxRate = 1 + ($tax->getTaxRate() / 100);

        return [
            'id' => Uuid::randomHex(),
            'productNumber' => 'SW_' . Uuid::randomHex(),
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 50 / $taxRate, 'linked' => true]],
            'purchasePrices' => [['currencyId' => Defaults::CURRENCY, 'gross' => 40, 'net' => 40 / $taxRate, 'linked' => true]],
            'name' => 'Product with simple price',
            'description' => 'This is a simple product description.',
            'taxId' => $tax->getId(),
            'manufacturerId' => $this->faker->randomElement($manufacturer),
            'active' => true,
            'height' => 30,
            'width' => 40,
            'categories' => $this->getCategoryByName('[Example products]'),
            'stock' => 5,
            'visibilities' => $visibilities,
            'cover' => ['mediaId' => Random::getRandomArrayElement($mediaIds)],
        ];
    }

    private function createStaticProductManyReviews(TaxCollection $taxes, array $manufacturer, array $visibilities, array $mediaIds): array
    {
        $tax = $taxes->get(array_rand($taxes->getIds()));
        \assert($tax instanceof TaxEntity);
        $taxRate = 1 + ($tax->getTaxRate() / 100);

        return [
            'id' => Uuid::randomHex(),
            'productNumber' => 'SW_' . Uuid::randomHex(),
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 249.99, 'net' => 249.99 / $taxRate, 'linked' => true]],
            'purchasePrices' => [['currencyId' => Defaults::CURRENCY, 'gross' => 220, 'net' => 220 / $taxRate, 'linked' => true]],
            'name' => 'Product with many reviews',
            'description' => 'This is a simple product description.',
            'taxId' => $tax->getId(),
            'manufacturerId' => $this->faker->randomElement($manufacturer),
            'active' => true,
            'height' => 30,
            'width' => 40,
            'categories' => $this->getCategoryByName('[Example products]'),
            'stock' => 20,
            'visibilities' => $visibilities,
            'cover' => ['mediaId' => Random::getRandomArrayElement($mediaIds)],
        ];
    }

    private function createStaticProductLongDescription(TaxCollection $taxes, array $manufacturer, array $visibilities, array $mediaIds): array
    {
        $tax = $taxes->get(array_rand($taxes->getIds()));
        \assert($tax instanceof TaxEntity);
        $taxRate = 1 + ($tax->getTaxRate() / 100);

        return [
            'id' => Uuid::randomHex(),
            'productNumber' => 'SW_' . Uuid::randomHex(),
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 249.99, 'net' => 249.99 / $taxRate, 'linked' => true]],
            'purchasePrices' => [['currencyId' => Defaults::CURRENCY, 'gross' => 220, 'net' => 220 / $taxRate, 'linked' => true]],
            'name' => 'Product with long description',
            'description' => $this->getLongDescription(),
            'taxId' => $tax->getId(),
            'manufacturerId' => $this->faker->randomElement($manufacturer),
            'active' => true,
            'height' => 30,
            'width' => 40,
            'categories' => $this->getCategoryByName('[Example products]'),
            'stock' => 20,
            'visibilities' => $visibilities,
            'cover' => ['mediaId' => Random::getRandomArrayElement($mediaIds)],
        ];
    }

    private function createStaticProductWithListPrice(TaxCollection $taxes, array $manufacturer, array $visibilities, array $mediaIds): array
    {
        $tax = $taxes->get(array_rand($taxes->getIds()));
        \assert($tax instanceof TaxEntity);
        $taxRate = 1 + ($tax->getTaxRate() / 100);

        // Base price
        $grossPrice = 199.99;
        $netPrice = $grossPrice / $taxRate;

        // List price (original price before discount)
        $listGrossPrice = 299.99;
        $listNetPrice = $listGrossPrice / $taxRate;

        return [
            'id' => Uuid::randomHex(),
            'productNumber' => 'SW_' . Uuid::randomHex(),
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => $grossPrice,
                    'net' => $netPrice,
                    'linked' => true,
                    'listPrice' => [
                        'gross' => $listGrossPrice,
                        'net' => $listNetPrice,
                        'linked' => true,
                    ],
                ],
            ],
            'purchasePrices' => [['currencyId' => Defaults::CURRENCY, 'gross' => 150, 'net' => 150 / $taxRate, 'linked' => true]],
            'name' => 'Product with list price',
            'description' => 'This product shows a strikethrough list price to indicate a discount.',
            'taxId' => $tax->getId(),
            'manufacturerId' => $this->faker->randomElement($manufacturer),
            'active' => true,
            'height' => 30,
            'width' => 40,
            'categories' => $this->getCategoryByName('[Example products]'),
            'stock' => 10,
            'visibilities' => $visibilities,
            'cover' => ['mediaId' => Random::getRandomArrayElement($mediaIds)],
        ];
    }

    private function getLongDescription(): string
    {
        return <<<HTML
            <div class="laptop-description">
                <h2>EliteBook Pro X1 - The Ultimate Laptop Experience</h2>

                <p>Experience unparalleled performance and style with the EliteBook Pro X1, designed for professionals who demand the best in mobile computing. With its sleek aluminum unibody design and cutting-edge technology, this laptop redefines what's possible in a portable device.</p>

                <h3>Key Features</h3>
                <ul>
                    <li><strong>Next-Gen Performance:</strong> Powered by the latest 12th Gen Intel® Core™ i9 processor</li>
                    <li><strong>Stunning Display:</strong> 14.2" Liquid Retina XDR display with ProMotion technology</li>
                    <li><strong>All-Day Battery Life:</strong> Up to 22 hours of battery life for uninterrupted productivity</li>
                    <li><strong>Advanced Cooling:</strong> Revolutionary thermal system keeps the laptop cool under pressure</li>
                    <li><strong>Professional Graphics:</strong> NVIDIA® GeForce RTX™ 3080 with 16GB GDDR6 memory</li>
                </ul>

                <h3>Technical Specifications</h3>
                <table class="specs-table table table-striped">
                    <tr>
                        <th>Component</th>
                        <th>Specification</th>
                    </tr>
                    <tr>
                        <td>Processor</td>
                        <td>12th Gen Intel® Core™ i9, 14-core CPU, up to 5.3GHz</td>
                    </tr>
                    <tr>
                        <td>Memory</td>
                        <td>32GB unified memory (configurable up to 64GB)</td>
                    </tr>
                    <tr>
                        <td>Storage</td>
                        <td>1TB SSD (configurable up to 8TB)</td>
                    </tr>
                    <tr>
                        <td>Display</td>
                        <td>14.2" Liquid Retina XDR, 3024 x 1964 resolution, 1,000 nits sustained brightness</td>
                    </tr>
                    <tr>
                        <td>Graphics</td>
                        <td>NVIDIA® GeForce RTX™ 3080 with 16GB GDDR6 memory</td>
                    </tr>
                    <tr>
                        <td>Battery Life</td>
                        <td>Up to 22 hours video playback, up to 17 hours wireless web</td>
                    </tr>
                    <tr>
                        <td>Ports</td>
                        <td>3x Thunderbolt 4 (USB-C), HDMI, SDXC card slot, 3.5mm headphone jack</td>
                    </tr>
                    <tr>
                        <td>Wireless</td>
                        <td>Wi-Fi 6E (802.11ax), Bluetooth 5.3</td>
                    </tr>
                </table>

                <h3>Designed for Professionals</h3>
                <p>The EliteBook Pro X1 features a stunning aluminum unibody design that's both lightweight and durable. The backlit keyboard with full-height function row and Touch ID provides a seamless typing experience, while the Force Touch trackpad offers precise cursor control and pressure-sensing capabilities.</p>

                <h3>Immersive Multimedia Experience</h3>
                <p>Enjoy your favorite content on the brilliant 14.2" Liquid Retina XDR display with ProMotion technology that automatically adjusts the refresh rate up to 120Hz for smoother scrolling and more responsive gaming. The six-speaker sound system with force-cancelling woofers delivers an immersive audio experience.</p>

                <h3>Eco-Friendly Design</h3>
                <p>We're committed to the environment, which is why the EliteBook Pro X1 is made with 100% recycled aluminum in the enclosure and 100% recycled rare earth elements in all magnets. It's also free of harmful substances like mercury, BFRs, PVC, and beryllium.</p>

                <p class="disclaimer">Specifications may vary by configuration. Battery life varies by use and configuration. See <a href="/batteries">battery information</a> for details.</p>
            </div>
        HTML;
    }
}
