<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Layout\Element\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Layout\Element\Format\Display;
use Shopware\Core\Content\ContentSystem\Layout\Element\Format\ElementFormat;
use Shopware\Core\Content\ContentSystem\Layout\Element\Format\Margin;
use Shopware\Core\Content\ContentSystem\Layout\Element\Format\Padding;

/**
 * @internal
 */
#[CoversClass(ElementFormat::class)]
class ElementFormatTest extends TestCase
{
    #[TestDox('maps format options by name and serializes to array')]
    public function testToArrayReturnsOptionsKeyedByName(): void
    {
        $format = new ElementFormat(
            new Display(xs: true, md: false),
            new Padding(sm: '10px', lg: '20px'),
        );

        static::assertSame([
            'display' => ['xs' => true, 'sm' => null, 'md' => false, 'lg' => null, 'xl' => null, 'xxl' => null],
            'padding' => ['xs' => null, 'sm' => '10px', 'md' => null, 'lg' => '20px', 'xl' => null, 'xxl' => null],
        ], $format->toArray());
    }

    #[TestDox('returns empty array when constructed with no options')]
    public function testToArrayReturnsEmptyArrayWithNoOptions(): void
    {
        $format = new ElementFormat();

        static::assertSame([], $format->toArray());
    }

    #[TestDox('last option wins when multiple options share the same name')]
    public function testLastOptionWinsForDuplicateName(): void
    {
        $format = new ElementFormat(
            new Margin(xs: '5px'),
            new Margin(xs: '15px'),
        );

        static::assertSame([
            'margin' => ['xs' => '15px', 'sm' => null, 'md' => null, 'lg' => null, 'xl' => null, 'xxl' => null],
        ], $format->toArray());
    }
}
