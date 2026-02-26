<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Element\Format;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[Package('discovery')]
final class FormatOptionRegistry
{
    /**
     * @param ServiceLocator<FormatOption> $locator
     */
    public function __construct(
        private readonly ServiceLocator $locator,
    ) {
    }

    /**
     * @return array<string, class-string<FormatOption>>
     */
    public function all(): array
    {
        /** @var array<string, class-string<FormatOption>> */
        return $this->locator->getProvidedServices();
    }
}
