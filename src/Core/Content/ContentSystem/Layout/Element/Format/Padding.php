<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Element\Format;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('discovery')]
final readonly class Padding extends FormatOption
{
    public static function name(): string
    {
        return 'padding';
    }

    public static function valueConstraints(): array
    {
        return [new Type('string'), new NotBlank()];
    }
}
