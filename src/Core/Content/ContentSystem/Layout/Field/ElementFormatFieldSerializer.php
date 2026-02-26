<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Field;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Layout\Element\Format\Breakpoint;
use Shopware\Core\Content\ContentSystem\Layout\Element\Format\ElementFormat;
use Shopware\Core\Content\ContentSystem\Layout\Element\Format\FormatOptionRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\AbstractFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
class ElementFormatFieldSerializer extends AbstractFieldSerializer
{
    public function __construct(
        ValidatorInterface $validator,
        DefinitionInstanceRegistry $definitionRegistry,
        private readonly FormatOptionRegistry $registry,
    ) {
        parent::__construct($validator, $definitionRegistry);
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof StorageAware) {
            throw ContentSystemException::invalidFieldType(StorageAware::class, $field::class);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        $value = $data->getValue();

        if ($value instanceof ElementFormat) {
            $value = $value->toArray();
        }

        if ($value !== null) {
            $value = Json::encode($value);
        }

        yield $field->getStorageName() => $value;
    }

    public function decode(Field $field, mixed $value): ?ElementFormat
    {
        if (!$field instanceof ElementFormatField) {
            throw ContentSystemException::invalidFieldType(ElementFormatField::class, $field::class);
        }

        if ($value === null) {
            return null;
        }

        if (\is_string($value)) {
            $value = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        }

        if (!\is_array($value)) {
            throw ContentSystemException::invalidFieldValueType('format', 'array', \gettype($value));
        }

        return $this->deserializeFormat($value);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function deserializeFormat(array $data): ElementFormat
    {
        $options = [];
        foreach ($this->registry->all() as $name => $class) {
            if (isset($data[$name]) && \is_array($data[$name])) {
                $options[] = new $class(...$data[$name]);
            }
        }

        return new ElementFormat(...$options);
    }

    /**
     * @return list<Constraint>
     */
    public function buildConstraints(Field $field): array
    {
        if (!$field instanceof ElementFormatField) {
            throw ContentSystemException::invalidFieldType(ElementFormatField::class, $field::class);
        }

        $breakpointKeyValidator = $this->buildBreakpointKeyCallback();

        $fields = [];
        foreach ($this->registry->all() as $name => $class) {
            $fields[$name] = new Optional([
                new Type('array'),
                $breakpointKeyValidator,
                new All($class::valueConstraints()),
            ]);
        }

        $constraints = [
            new Type('array'),
            new Collection(
                fields: $fields,
                allowExtraFields: false,
                allowMissingFields: true,
            ),
        ];

        if ($field->is(Required::class)) {
            $constraints[] = new NotBlank();
        }

        return $constraints;
    }

    protected function getConstraints(Field $field): array
    {
        return $this->buildConstraints($field);
    }

    private function buildBreakpointKeyCallback(): Callback
    {
        $allowedKeys = Breakpoint::values();

        return new Callback(static function (mixed $value, ExecutionContextInterface $context) use ($allowedKeys): void {
            if (!\is_array($value)) {
                return;
            }

            $invalidKeys = array_diff(array_keys($value), $allowedKeys);

            foreach ($invalidKeys as $invalidKey) {
                $context->buildViolation('The key "{{ key }}" is not allowed. Allowed keys: {{ allowed }}.')
                    ->setParameter('{{ key }}', (string) $invalidKey)
                    ->setParameter('{{ allowed }}', implode(', ', $allowedKeys))
                    ->atPath('[' . $invalidKey . ']')
                    ->addViolation();
            }
        });
    }
}
