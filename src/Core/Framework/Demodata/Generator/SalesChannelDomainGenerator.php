<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelDomainGenerator implements DemodataGeneratorInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityWriterInterface $writer,
        private readonly SalesChannelDomainDefinition $salesChannelDomainDefinition
    ) {
    }

    public function getDefinition(): string
    {
        return SalesChannelDomainDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $context->getConsole()->progressStart($numberOfItems);

        dump('>>>>>>>>>>>>>>>>>> bubbedibabbedi');

        $context->getConsole()->progressFinish();
    }
}
