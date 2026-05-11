<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use ScssPhp\ScssPhp\OutputStyle;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Storefront\Theme\CompilerConfiguration;
use Shopware\Storefront\Theme\ScssPhpCompiler;

/**
 * @internal
 */
#[CoversClass(ScssPhpCompiler::class)]
class ScssPhpCompilerTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('compilationProvider')]
    #[TestDox('compiles SCSS source for $_dataName')]
    public function testCompilesScss(array $config, string $scss, string $expected): void
    {
        $scssCompiler = new ScssPhpCompiler();

        $compiled = $scssCompiler->compileString(new CompilerConfiguration($config), $scss);

        static::assertSame($expected, trim((string) preg_replace('/\r?\n\s*/', ' ', $compiled)));
    }

    public static function compilationProvider(): \Generator
    {
        yield 'empty config (default expanded output)' => [
            [],
            '$background: #123456; body { background-color: $background; }',
            'body { background-color: #123456; }',
        ];

        yield 'compressed output style' => [
            ['outputStyle' => OutputStyle::COMPRESSED, 'importPaths' => []],
            '$background: #123456; body { background-color: $background; }',
            'body{background-color:#123456}',
        ];
    }

    #[TestDox('throws when the deprecated $cacheOptions argument is passed and the v6.8.0.0 flag is active')]
    public function testThrowsWhenCacheOptionsAreProvidedWithMajorFlagActive(): void
    {
        static::expectException(FeatureException::class);
        static::expectExceptionMessage('Passing $cacheOptions to Shopware\Storefront\Theme\ScssPhpCompiler::__construct() will be removed in v6.8.0');

        new ScssPhpCompiler(['cache_dir' => '/tmp/anywhere']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    #[TestDox('still compiles when $cacheOptions is passed and the v6.8.0.0 flag is disabled')]
    public function testStillCompilesWhenCacheOptionsAreProvidedWithMajorFlagDisabled(): void
    {
        $scssCompiler = new ScssPhpCompiler(['cache_dir' => '/tmp/anywhere']);

        $compiled = $scssCompiler->compileString(
            new CompilerConfiguration(['outputStyle' => OutputStyle::COMPRESSED]),
            'body { color: #fff; }'
        );

        static::assertSame('body{color:#fff}', rtrim($compiled));
    }
}
