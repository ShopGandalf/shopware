<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Element\Format;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
enum Breakpoint: string
{
    case XS = 'xs';
    case SM = 'sm';
    case MD = 'md';
    case LG = 'lg';
    case XL = 'xl';
    case XXL = 'xxl';

    /**
     * @codeCoverageIgnore
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
