<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Element\Format;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

/**
 * @internal
 */
#[Package('discovery')]
abstract readonly class FormatOption
{
    public function __construct(
        public string|bool|float|null $xs = null,
        public string|bool|float|null $sm = null,
        public string|bool|float|null $md = null,
        public string|bool|float|null $lg = null,
        public string|bool|float|null $xl = null,
        public string|bool|float|null $xxl = null,
    ) {
    }

    abstract public static function name(): string;

    /**
     * @return list<Constraint>
     */
    abstract public static function valueConstraints(): array;

    /**
     * @return array<string, string|bool|float|null>
     */
    public function toArray(): array
    {
        return [
            'xs' => $this->xs,
            'sm' => $this->sm,
            'md' => $this->md,
            'lg' => $this->lg,
            'xl' => $this->xl,
            'xxl' => $this->xxl,
        ];
    }
}
