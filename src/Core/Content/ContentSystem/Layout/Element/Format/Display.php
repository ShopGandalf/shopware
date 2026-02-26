<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Element\Format;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('discovery')]
final readonly class Display extends FormatOption
{
    public static function name(): string
    {
        return 'display';
    }

    public static function valueConstraints(): array
    {
        return [new Type('bool')];
    }
}
