<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal - may be changed in the future
 */
#[Package('framework')]
class ScssPhpCompiler extends AbstractScssCompiler
{
    private readonly Compiler $compiler;

    private readonly Filesystem $filesystem;

    /**
     * @param array<string, mixed>|null $cacheOptions
     *
     * @deprecated tag:v6.8.0 - reason:becomes-internal - $cacheOptions parameter will be removed; the scssphp v2.x library has no integrated cache. Register the {@see CachedScssCompiler} decorator instead.
     */
    public function __construct(
        ?array $cacheOptions = null,
        ?Compiler $compiler = null,
        ?Filesystem $filesystem = null,
    ) {
        if ($cacheOptions !== null) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                \sprintf(
                    'Passing $cacheOptions to %s::__construct() will be removed in v6.8.0; scssphp v2.x has no integrated cache. Register the %s decorator instead.',
                    self::class,
                    CachedScssCompiler::class,
                ),
            );
        }

        $this->compiler = $compiler ?? new Compiler();
        $this->filesystem = $filesystem ?? new Filesystem();
    }

    /**
     * Resets the injected Compiler's settable state to its scssphp defaults so consecutive
     * compileString() calls don't inherit each other's output style or import paths.
     */
    public function reset(): void
    {
        $this->compiler->setOutputStyle(OutputStyle::EXPANDED);
        $this->compiler->setImportPaths([]);
    }

    public function compileString(AbstractCompilerConfiguration $config, string $scss, ?string $path = null): string
    {
        $this->reset();

        $outputStyle = $config->getValue('outputStyle');
        if ($outputStyle === OutputStyle::COMPRESSED || $outputStyle === OutputStyle::EXPANDED) {
            $this->compiler->setOutputStyle($outputStyle);
        }

        $importPaths = $config->getValue('importPaths');
        if ($importPaths !== null) {
            $this->compiler->setImportPaths($importPaths);
        }

        if ($path !== null && $this->filesystem->exists($path)) {
            return $this->compiler->compileFile($path)->getCss();
        }

        return $this->compiler->compileString($scss)->getCss();
    }
}
