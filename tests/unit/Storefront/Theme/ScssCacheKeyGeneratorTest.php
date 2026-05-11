<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use ScssPhp\ScssPhp\OutputStyle;
use Shopware\Storefront\Theme\ScssCacheKeyGenerator;

/**
 * @internal
 */
#[CoversClass(ScssCacheKeyGenerator::class)]
class ScssCacheKeyGeneratorTest extends TestCase
{
    private Filesystem $filesystem;

    private ScssCacheKeyGenerator $generator;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->generator = new ScssCacheKeyGenerator($this->filesystem);
    }

    #[TestDox('produces a stable, prefixed key for identical inputs')]
    public function testGenerateIsStableForIdenticalInputs(): void
    {
        $key1 = $this->generator->generate('body{color:red}', null, OutputStyle::COMPRESSED, []);
        $key2 = $this->generator->generate('body{color:red}', null, OutputStyle::COMPRESSED, []);

        static::assertSame($key1, $key2);
        static::assertStringStartsWith('scss_compiler_', $key1);
    }

    /**
     * @param array{0: string, 1: ?string, 2: OutputStyle, 3: array<int|string, mixed>} $argsA
     * @param array{0: string, 1: ?string, 2: OutputStyle, 3: array<int|string, mixed>} $argsB
     */
    #[DataProvider('changingInputProvider')]
    #[TestDox('produces a different key when $_dataName changes')]
    public function testGenerateChangesWhenInputDiffers(array $argsA, array $argsB): void
    {
        $a = $this->generator->generate(...$argsA);
        $b = $this->generator->generate(...$argsB);

        static::assertNotSame($a, $b);
    }

    public static function changingInputProvider(): \Generator
    {
        yield 'scss source' => [
            ['body{color:red}', null, OutputStyle::COMPRESSED, []],
            ['body{color:blue}', null, OutputStyle::COMPRESSED, []],
        ];

        yield 'output style' => [
            ['body{color:red}', null, OutputStyle::COMPRESSED, []],
            ['body{color:red}', null, OutputStyle::EXPANDED, []],
        ];

        yield 'path' => [
            ['body{color:red}', '/some/path/a', OutputStyle::COMPRESSED, []],
            ['body{color:red}', '/some/path/b', OutputStyle::COMPRESSED, []],
        ];

        yield 'import path closure identity' => [
            ['body{color:red}', null, OutputStyle::COMPRESSED, [static fn () => 'a']],
            ['body{color:red}', null, OutputStyle::COMPRESSED, [static fn () => 'b']],
        ];

        yield 'import path non-closure object identity' => [
            ['body{color:red}', null, OutputStyle::COMPRESSED, [new \stdClass()]],
            ['body{color:red}', null, OutputStyle::COMPRESSED, [new \stdClass()]],
        ];

        yield 'import path nested array contents' => [
            ['body{color:red}', null, OutputStyle::COMPRESSED, [['a', 'b']]],
            ['body{color:red}', null, OutputStyle::COMPRESSED, [['c', 'd']]],
        ];
    }

    #[TestDox('includes the entry-point mtime in the key when the path exists on disk')]
    public function testGenerateIncludesPathMtimeWhenPathExists(): void
    {
        $this->filesystem->write('/theme/all.scss', 'body{color:red}', ['timestamp' => 1000]);
        $a = $this->generator->generate('body{color:red}', '/theme/all.scss', OutputStyle::COMPRESSED, []);

        $this->filesystem->write('/theme/all.scss', 'body{color:red}', ['timestamp' => 2000]);
        $b = $this->generator->generate('body{color:red}', '/theme/all.scss', OutputStyle::COMPRESSED, []);

        static::assertNotSame($a, $b);
    }

    #[TestDox('changes the key when the mtime of an imported file changes')]
    public function testGenerateChangesWhenImportedFileMtimeChanges(): void
    {
        $this->filesystem->write('/base/_partial.scss', '.x{color:red}', ['timestamp' => 1000]);
        $scss = '@import "partial";';

        $first = $this->generator->generate($scss, null, OutputStyle::COMPRESSED, ['/base']);

        $this->filesystem->write('/base/_partial.scss', '.x{color:red}', ['timestamp' => 2000]);
        $second = $this->generator->generate($scss, null, OutputStyle::COMPRESSED, ['/base']);

        static::assertNotSame($first, $second);
    }

    #[DataProvider('moduleDirectiveProvider')]
    #[TestDox('resolves $_dataName statements against the configured base paths')]
    public function testFindImportsResolvesModuleDirectives(string $scss): void
    {
        $this->filesystem->write('/base/_button.scss', '.btn{color:red}');

        $imports = $this->generator->findImports($scss, ['/base']);

        static::assertSame(['/base/_button.scss'], $imports);
    }

    public static function moduleDirectiveProvider(): \Generator
    {
        yield '@import' => ['@import "button";'];
        yield '@use' => ['@use "button";'];
        yield '@forward' => ['@forward "button";'];
        yield '@use with alias' => ['@use "button" as btn;'];
        yield '@use with config' => ['@use "button" with ($primary: red);'];
        yield '@forward with show' => ['@forward "button" show $primary;'];
    }

    #[TestDox('walks nested @import chains recursively')]
    public function testFindImportsFollowsNestedImports(): void
    {
        $this->filesystem->write('/base/_root.scss', '@import \'leaf\';');
        $this->filesystem->write('/base/_leaf.scss', '.leaf{color:green}');
        $scss = '@import "root";';

        $imports = $this->generator->findImports($scss, ['/base']);

        static::assertContains('/base/_root.scss', $imports);
        static::assertContains('/base/_leaf.scss', $imports);
    }

    #[TestDox('resolves absolute @import paths even with no base paths configured')]
    public function testFindImportsResolvesAbsolutePathsWithoutBase(): void
    {
        $this->filesystem->write('/abs/abs.scss', '.abs{}');
        $scss = '@import \'/abs/abs.scss\';';

        $imports = $this->generator->findImports($scss, []);

        static::assertSame(['/abs/abs.scss'], $imports);
    }

    #[TestDox('terminates instead of looping when @import chains are circular')]
    public function testFindImportsBreaksOnCircularImports(): void
    {
        $this->filesystem->write('/base/_a.scss', '@import \'b\';');
        $this->filesystem->write('/base/_b.scss', '@import \'a\';');
        $scss = '@import "a";';

        $imports = $this->generator->findImports($scss, ['/base']);

        static::assertCount(2, $imports);
    }

    #[TestDox('silently skips @import targets that cannot be resolved')]
    public function testFindImportsIgnoresUnresolvableImports(): void
    {
        $scss = '@import "missing";';

        $imports = $this->generator->findImports($scss, ['/base']);

        static::assertSame([], $imports);
    }
}
