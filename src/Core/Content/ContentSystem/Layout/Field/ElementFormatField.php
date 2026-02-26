<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class ElementFormatField extends JsonField
{
    protected function getSerializerClass(): string
    {
        return ElementFormatFieldSerializer::class;
    }
}
