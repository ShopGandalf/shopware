<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\System\Language\SalesChannelLanguageLoader;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelLanguage\SalesChannelLanguageDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;
use Shopware\Elasticsearch\Product\ElasticsearchProductDefinition;
use Shopware\Elasticsearch\Product\SalesChannelLanguageSubscriber;

/**
 * @internal
 */
#[CoversClass(SalesChannelLanguageSubscriber::class)]
class SalesChannelLanguageSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame([
            'sales_channel_language.written' => 'onSalesChannelLanguage',
            'sales_channel_language.deleted' => 'onSalesChannelLanguage',
        ], SalesChannelLanguageSubscriber::getSubscribedEvents());
    }

    public function testOnSalesChannelLanguageWithoutEsEnabled(): void
    {
        $esHelper = $this->createMock(ElasticsearchHelper::class);
        $esHelper->expects($this->once())->method('allowIndexing')->willReturn(false);

        $salesChannelLanguageLoader = $this->createMock(SalesChannelLanguageLoader::class);
        $salesChannelLanguageLoader->expects($this->never())->method('reset');

        $subscriber = new SalesChannelLanguageSubscriber(
            $esHelper,
            $this->createMock(ElasticsearchRegistry::class),
            $this->createMock(Client::class),
            $salesChannelLanguageLoader,
        );

        $event = $this->createMock(EntityWrittenEvent::class);
        $event
            ->expects($this->never())
            ->method('getEntityName');

        $subscriber->onSalesChannelLanguage($event);
    }

    public function testOnSalesChannelLanguageWithWrongEntityName(): void
    {
        $esHelper = $this->createMock(ElasticsearchHelper::class);
        $esHelper->expects($this->once())->method('allowIndexing')->willReturn(true);

        $salesChannelLanguageLoader = $this->createMock(SalesChannelLanguageLoader::class);
        $salesChannelLanguageLoader->expects($this->never())->method('reset');

        $subscriber = new SalesChannelLanguageSubscriber(
            $esHelper,
            $this->createMock(ElasticsearchRegistry::class),
            $this->createMock(Client::class),
            $salesChannelLanguageLoader,
        );

        $event = $this->createMock(EntityWrittenEvent::class);
        $event
            ->expects($this->once())
            ->method('getEntityName')
            ->willReturn('wrong_entity');

        $subscriber->onSalesChannelLanguage($event);
    }

    public function testOnSalesChannelLanguageWithInsertOperation(): void
    {
        $context = Context::createDefaultContext();
        $esHelper = $this->createMock(ElasticsearchHelper::class);
        $esHelper->expects($this->once())->method('allowIndexing')->willReturn(true);
        $esHelper->expects($this->once())->method('getIndexName')->willReturn('sw_product');

        $client = $this->createMock(Client::class);
        $registry = $this->createMock(ElasticsearchRegistry::class);
        $esProductDefinition = $this->createMock(ElasticsearchProductDefinition::class);
        $esProductDefinition->expects($this->once())->method('getEntityDefinition')->willReturn(new ProductDefinition());
        $esProductDefinition->expects($this->once())->method('getMapping')->with($context)->willReturn([
            'properties' => [
                'field1' => 'test1',
                'field2' => 'test2',
            ],
        ]);
        $registry->expects($this->once())->method('getDefinitions')->willReturn([$esProductDefinition]);

        $namespace = $this->createMock(IndicesNamespace::class);
        $namespace->expects($this->once())->method('putMapping')->with([
            'index' => 'sw_product',
            'body' => [
                'properties' => [
                    'field1' => 'test1',
                    'field2' => 'test2',
                ],
            ],
        ]);

        $namespace->expects($this->once())->method('exists')->with(['index' => 'sw_product'])->willReturn(true);

        $client->method('indices')->willReturn($namespace);

        $salesChannelLanguageLoader = $this->createMock(SalesChannelLanguageLoader::class);
        $salesChannelLanguageLoader->expects($this->once())->method('reset');

        $subscriber = new SalesChannelLanguageSubscriber(
            $esHelper,
            $registry,
            $client,
            $salesChannelLanguageLoader,
        );

        $event = $this->createMock(EntityWrittenEvent::class);
        $event
            ->expects($this->once())
            ->method('getEntityName')
            ->willReturn(SalesChannelLanguageDefinition::ENTITY_NAME);
        $event
            ->expects($this->once())
            ->method('getContext')
            ->willReturn($context);

        $subscriber->onSalesChannelLanguage($event);
    }

    public function testOnSalesChannelLanguageWithDeleteOperation(): void
    {
        $context = Context::createDefaultContext();
        $esHelper = $this->createMock(ElasticsearchHelper::class);
        $esHelper->expects($this->once())->method('allowIndexing')->willReturn(true);
        $esHelper->expects($this->once())->method('getIndexName')->willReturn('sw_product');

        $client = $this->createMock(Client::class);
        $registry = $this->createMock(ElasticsearchRegistry::class);
        $esProductDefinition = $this->createMock(ElasticsearchProductDefinition::class);
        $esProductDefinition->expects($this->once())->method('getEntityDefinition')->willReturn(new ProductDefinition());
        $esProductDefinition->expects($this->once())->method('getMapping')->with($context)->willReturn([
            'properties' => [
                'field1' => 'test1',
            ],
        ]);
        $registry->expects($this->once())->method('getDefinitions')->willReturn([$esProductDefinition]);

        $namespace = $this->createMock(IndicesNamespace::class);
        $namespace->expects($this->once())->method('putMapping')->with([
            'index' => 'sw_product',
            'body' => [
                'properties' => [
                    'field1' => 'test1',
                ],
            ],
        ]);

        $namespace->expects($this->once())->method('exists')->with(['index' => 'sw_product'])->willReturn(true);

        $client->method('indices')->willReturn($namespace);

        $salesChannelLanguageLoader = $this->createMock(SalesChannelLanguageLoader::class);
        $salesChannelLanguageLoader->expects($this->once())->method('reset');

        $subscriber = new SalesChannelLanguageSubscriber(
            $esHelper,
            $registry,
            $client,
            $salesChannelLanguageLoader,
        );

        $event = $this->createMock(EntityDeletedEvent::class);
        $event
            ->expects($this->once())
            ->method('getEntityName')
            ->willReturn(SalesChannelLanguageDefinition::ENTITY_NAME);
        $event
            ->expects($this->once())
            ->method('getContext')
            ->willReturn($context);

        $subscriber->onSalesChannelLanguage($event);
    }
}
