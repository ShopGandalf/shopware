<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Element\Format;

use Shopware\Core\Framework\Log\Package;

/**
 * Responsive format settings for a ContentElement, keyed per breakpoint.
 *
 * @internal
 */
#[Package('discovery')]
final readonly class ElementFormat
{
    /**
     * @var array<string, FormatOption>
     */
    private array $options;

    public function __construct(FormatOption ...$options)
    {
        $map = [];
        foreach ($options as $option) {
            $map[$option::name()] = $option;
        }
        $this->options = $map;
    }

    /**
     * @return array<string, array<string, string|bool|float|null>>
     */
    public function toArray(): array
    {
        $data = [];
        foreach ($this->options as $name => $option) {
            $data[$name] = $option->toArray();
        }

        return $data;
    }
}
