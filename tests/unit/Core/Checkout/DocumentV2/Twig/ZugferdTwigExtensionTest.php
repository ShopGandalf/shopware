<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Twig\ZugferdTwigExtension;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ZugferdTwigExtension::class)]
class ZugferdTwigExtensionTest extends TestCase
{
    private Environment $twig;

    private ZugferdTwigExtension $extension;

    protected function setUp(): void
    {
        $this->twig = new Environment(new ArrayLoader([]));
        $this->extension = new ZugferdTwigExtension();

        $this->twig->addExtension($this->extension);
    }

    #[DataProvider('decimalProvider')]
    public function testZugferdDecimal(float|int|string|null $input, int $places, string $expected): void
    {
        static::assertSame(
            $expected,
            $this->extension->zugferdDecimal($input, $places)
        );
    }

    /**
     * @return iterable<string, array{float|int|string|null, int, string}>
     */
    public static function decimalProvider(): iterable
    {
        yield 'two_decimals' => [1.005, 2, '1.01'];
        yield 'rounds_down' => [1.004, 2, '1.00'];
        yield 'zero' => [0, 2, '0.00'];
        yield 'negative' => [-12.5, 2, '-12.50'];
        yield 'string_input' => ['3.14159', 2, '3.14'];
        yield 'null_input' => [null, 2, '0.00'];
        yield 'four_decimals' => [1.23456, 4, '1.2346'];
        yield 'large_number' => [1234567.89, 2, '1234567.89'];
    }

    public function testZugferdDecimalIsAvailableAsTwigFilter(): void
    {
        $this->twig->setLoader(new ArrayLoader([
            't' => '{{ v|zugferd_decimal }}',
        ]));

        static::assertSame(
            '19.95',
            $this->twig->render('t', ['v' => 19.95])
        );
    }

    #[DataProvider('dateProvider')]
    public function testZugferdDate102(\DateTimeInterface|string|null $input, string $expected): void
    {
        static::assertSame(
            $expected,
            $this->extension->zugferdDate102($input)
        );
    }

    /**
     * @return iterable<string, array{\DateTimeInterface|string|null, string}>
     */
    public static function dateProvider(): iterable
    {
        yield 'datetime_immutable' => [new \DateTimeImmutable('2026-05-11T08:00:00+00:00'), '20260511'];
        yield 'datetime_mutable' => [new \DateTime('2026-12-31T23:59:00Z'), '20261231'];
        yield 'iso_string' => ['2026-01-01T00:00:00+00:00', '20260101'];
        yield 'storage_format' => ['2026-05-05 12:00:00.000', '20260505'];
        yield 'null' => [null, ''];
    }

    public function testZugferdDate102IsAvailableAsTwigFilter(): void
    {
        $this->twig->setLoader(new ArrayLoader([
            't' => '{{ v|zugferd_date_102 }}',
        ]));

        static::assertSame(
            '20260511',
            $this->twig->render('t', [
                'v' => new \DateTimeImmutable('2026-05-11T08:00:00+00:00'),
            ]),
        );
    }

    #[DataProvider('htmlAutoescapeProducesValidXmlEntitiesProvider')]
    public function testTwigHtmlAutoescapeProducesValidXmlEntities(string $input, string $expected): void
    {
        $this->twig->setLoader(new ArrayLoader([
            't' => '{% autoescape "html" %}{{ v }}{% endautoescape %}',
        ]));

        static::assertSame(
            $expected,
            $this->twig->render('t', ['v' => $input])
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function htmlAutoescapeProducesValidXmlEntitiesProvider(): iterable
    {
        yield 'ampersand' => ['First & Second',  'First &amp; Second'];
        yield 'lt' => ['1 < 2',        '1 &lt; 2'];
        yield 'gt' => ['2 > 1',        '2 &gt; 1'];
        yield 'double' => ['He said "hi"', 'He said &quot;hi&quot;'];
        yield 'single' => ['it\'s',         'it&#039;s'];
        yield 'plain' => ['hello',        'hello'];
        yield 'empty' => ['',             ''];
        yield 'unicode' => ['Schöppingen',  'Schöppingen'];
    }
}
