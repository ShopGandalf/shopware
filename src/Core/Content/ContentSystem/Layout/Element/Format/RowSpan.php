<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Element\Format;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('discovery')]
final readonly class RowSpan extends FormatOption
{
    public static function name(): string
    {
        return 'row-span';
    }

    public static function valueConstraints(): array
    {
        return [new Type('integer'), new GreaterThan(0)];
    }
}
