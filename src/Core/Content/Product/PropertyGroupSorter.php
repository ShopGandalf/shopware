<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('inventory')]
class PropertyGroupSorter extends AbstractPropertyGroupSorter
{
    public function getDecorated(): AbstractPropertyGroupSorter
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @deprecated tag:v6.8.0 - reason:new-optional-parameter - parameter $localeCode will be added
     */
    public function sort(EntityCollection $options /* ?string $localeCode = null */): PropertyGroupCollection
    {
        $localeCode = \func_num_args() === 2 ? func_get_arg(1) : null;

        $sorted = [];

        foreach ($options as $option) {
            $origin = $option->get('group');

            if ($origin === null || $origin->get('visibleOnProductDetailPage') === false) {
                continue;
            }

            $group = clone $origin;

            $groupId = $group->get('id');
            if (\array_key_exists($groupId, $sorted)) {
                \assert($sorted[$groupId]->get('options') !== null);
                $sorted[$groupId]->get('options')->add($option);

                continue;
            }

            if ($group->get('options') === null) {
                $group->assign([
                    'options' => new PropertyGroupOptionCollection(),
                ]);
            }

            \assert($group->get('options') !== null);
            $group->get('options')->add($option);

            $sorted[$groupId] = $group;
        }

        $collection = new PropertyGroupCollection($sorted);
        $collection->sortByPositions();
        $collection->sortByConfig($localeCode);

        return $collection;
    }
}
