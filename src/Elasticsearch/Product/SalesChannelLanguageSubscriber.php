<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use OpenSearch\Client;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\SalesChannelLanguageLoader;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelLanguage\SalesChannelLanguageDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * When a language is added or removed from a sales channel, we need to update Elasticsearch mappings
 * to include/exclude language fields for that language.
 * Also handles sales channel deletion, which cascades to sales_channel_language deletions.
 */
#[Package('framework')]
class SalesChannelLanguageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ElasticsearchHelper $elasticsearchHelper,
        private readonly ElasticsearchRegistry $registry,
        private readonly Client $client,
        private readonly SalesChannelLanguageLoader $salesChannelLanguageLoader,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'sales_channel_language.written' => 'onSalesChannelLanguage',
            'sales_channel_language.deleted' => 'onSalesChannelLanguage',
        ];
    }

    public function onSalesChannelLanguage(EntityWrittenEvent|EntityDeletedEvent $event): void
    {
        if (!$this->elasticsearchHelper->allowIndexing()) {
            return;
        }

        if ($event->getEntityName() !== SalesChannelLanguageDefinition::ENTITY_NAME) {
            return;
        }

        $this->updateMappings($event->getContext());
    }

    private function updateMappings(Context $context): void
    {
        $this->salesChannelLanguageLoader->reset();

        foreach ($this->registry->getDefinitions() as $definition) {
            $indexName = $this->elasticsearchHelper->getIndexName($definition->getEntityDefinition());

            if (!$this->client->indices()->exists(['index' => $indexName])) {
                continue;
            }

            $newMapping = $definition->getMapping($context)['properties'];

            $this->client->indices()->putMapping([
                'index' => $indexName,
                'body' => [
                    'properties' => $newMapping,
                ],
            ]);
        }
    }
}
