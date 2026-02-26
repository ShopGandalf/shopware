<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Element\Format;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('discovery')]
final readonly class JustifySelf extends FormatOption
{
    public static function name(): string
    {
        return 'justify-self';
    }

    public static function valueConstraints(): array
    {
        return [new Type('string'), new NotBlank()];
    }
}
