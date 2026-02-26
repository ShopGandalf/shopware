<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Layout\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Layout\Element\Format\ColSpan;
use Shopware\Core\Content\ContentSystem\Layout\Element\Format\Display;
use Shopware\Core\Content\ContentSystem\Layout\Element\Format\ElementFormat;
use Shopware\Core\Content\ContentSystem\Layout\Element\Format\FormatOptionRegistry;
use Shopware\Core\Content\ContentSystem\Layout\Element\Format\Padding;
use Shopware\Core\Content\ContentSystem\Layout\Field\ElementFormatField;
use Shopware\Core\Content\ContentSystem\Layout\Field\ElementFormatFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ElementFormatFieldSerializer::class)]
class ElementFormatFieldSerializerTest extends TestCase
{
    private ElementFormatFieldSerializer $serializer;

    private EntityExistence $existence;

    private WriteParameterBag $parameters;

    protected function setUp(): void
    {
        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);

        $registry = new FormatOptionRegistry(new ServiceLocator([
            'display' => static fn (): Display => new Display(),
            'col-span' => static fn (): ColSpan => new ColSpan(),
            'padding' => static fn (): Padding => new Padding(),
        ]));

        $this->serializer = new ElementFormatFieldSerializer($validator, $definitionRegistry, $registry);
        $this->existence = new EntityExistence('content_layout', ['id' => 'test'], true, false, false, []);
        $this->parameters = static::createStub(WriteParameterBag::class);
    }

    #[TestDox('encodes ElementFormat object to JSON string')]
    public function testEncodeWithElementFormatYieldsJson(): void
    {
        $field = $this->createField();
        $format = new ElementFormat(new Display(xs: true));
        $kvPair = new KeyValuePair('format', $format, false);

        $result = iterator_to_array($this->serializer->encode($field, $this->existence, $kvPair, $this->parameters));

        static::assertArrayHasKey('format', $result);
        static::assertIsString($result['format']);

        $decoded = json_decode($result['format'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);
        static::assertArrayHasKey('display', $decoded);
        static::assertTrue($decoded['display']['xs']);
    }

    #[TestDox('encodes plain array value to JSON string')]
    public function testEncodeWithArrayYieldsJson(): void
    {
        $field = $this->createField();
        $data = ['display' => ['xs' => false]];
        $kvPair = new KeyValuePair('format', $data, false);

        $result = iterator_to_array($this->serializer->encode($field, $this->existence, $kvPair, $this->parameters));

        static::assertArrayHasKey('format', $result);
        $decoded = json_decode($result['format'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame($data, $decoded);
    }

    #[TestDox('encodes null value as null')]
    public function testEncodeWithNullYieldsNull(): void
    {
        $field = $this->createField();
        $kvPair = new KeyValuePair('format', null, false);

        $result = iterator_to_array($this->serializer->encode($field, $this->existence, $kvPair, $this->parameters));

        static::assertArrayHasKey('format', $result);
        static::assertNull($result['format']);
    }

    #[TestDox('throws exception when encode receives non-StorageAware field')]
    public function testEncodeThrowsOnNonStorageAwareField(): void
    {
        $invalidField = new TranslatedField('format');
        $invalidField->compile(static::createStub(DefinitionInstanceRegistry::class));

        $kvPair = new KeyValuePair('format', null, false);

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(
                \Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware::class,
                TranslatedField::class
            )
        );

        iterator_to_array($this->serializer->encode($invalidField, $this->existence, $kvPair, $this->parameters));
    }

    #[TestDox('decodes JSON string to ElementFormat')]
    public function testDecodeJsonStringReturnsElementFormat(): void
    {
        $field = $this->createField();
        $json = json_encode(['display' => ['xs' => true, 'sm' => null, 'md' => null, 'lg' => null, 'xl' => null, 'xxl' => null]], \JSON_THROW_ON_ERROR);

        $result = $this->serializer->decode($field, $json);

        static::assertInstanceOf(ElementFormat::class, $result);
        $array = $result->toArray();
        static::assertArrayHasKey('display', $array);
        static::assertTrue($array['display']['xs']);
    }

    #[TestDox('decodes null to null')]
    public function testDecodeNullReturnsNull(): void
    {
        $field = $this->createField();

        static::assertNull($this->serializer->decode($field, null));
    }

    #[TestDox('throws exception when decode receives non-ElementFormatField')]
    public function testDecodeThrowsOnNonElementFormatField(): void
    {
        $invalidField = new JsonField('format', 'format');
        $invalidField->compile(static::createStub(DefinitionInstanceRegistry::class));

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(ElementFormatField::class, JsonField::class)
        );

        $this->serializer->decode($invalidField, '{}');
    }

    #[TestDox('throws exception when decode receives non-array JSON value')]
    public function testDecodeThrowsOnNonArrayDecodedValue(): void
    {
        $field = $this->createField();

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('format', 'array', 'string')
        );

        $this->serializer->decode($field, '"not-an-array"');
    }

    #[TestDox('deserializes format data with registered options into ElementFormat')]
    public function testDeserializeFormatCreatesElementFormatFromRegisteredOptions(): void
    {
        $data = [
            'display' => ['xs' => true, 'sm' => null, 'md' => null, 'lg' => null, 'xl' => null, 'xxl' => null],
            'padding' => ['xs' => '10px', 'sm' => null, 'md' => null, 'lg' => null, 'xl' => null, 'xxl' => null],
        ];

        $result = $this->serializer->deserializeFormat($data);

        static::assertSame($data, $result->toArray());
    }

    #[TestDox('ignores unknown keys when deserializing format data')]
    public function testDeserializeFormatIgnoresUnknownKeys(): void
    {
        $data = [
            'display' => ['xs' => false, 'sm' => null, 'md' => null, 'lg' => null, 'xl' => null, 'xxl' => null],
            'unknown-option' => ['xs' => 'value'],
        ];

        $result = $this->serializer->deserializeFormat($data);

        $array = $result->toArray();
        static::assertArrayHasKey('display', $array);
        static::assertArrayNotHasKey('unknown-option', $array);
    }

    #[TestDox('returns empty ElementFormat when deserializing empty data')]
    public function testDeserializeFormatReturnsEmptyFormatForEmptyData(): void
    {
        $result = $this->serializer->deserializeFormat([]);

        static::assertSame([], $result->toArray());
    }

    #[TestDox('builds constraints with registry-driven optional fields and All-wrapped value constraints')]
    public function testBuildConstraintsReturnsRegistryDrivenConstraints(): void
    {
        $field = $this->createField();

        $constraints = $this->serializer->buildConstraints($field);

        static::assertCount(2, $constraints);
        static::assertInstanceOf(Type::class, $constraints[0]);
        static::assertSame('array', $constraints[0]->type);
        static::assertInstanceOf(Collection::class, $constraints[1]);

        $collection = $constraints[1];
        static::assertArrayHasKey('display', $collection->fields);
        static::assertArrayHasKey('col-span', $collection->fields);
        static::assertArrayHasKey('padding', $collection->fields);
        static::assertFalse($collection->allowExtraFields);
        static::assertTrue($collection->allowMissingFields);

        $displayField = $collection->fields['display'];
        static::assertInstanceOf(Optional::class, $displayField);
        static::assertIsArray($displayField->constraints);
        static::assertCount(3, $displayField->constraints);
        static::assertInstanceOf(Type::class, $displayField->constraints[0]);
        static::assertInstanceOf(All::class, $displayField->constraints[2]);
    }

    #[TestDox('appends NotBlank constraint when field has Required flag')]
    public function testBuildConstraintsAddsNotBlankWhenRequired(): void
    {
        $field = $this->createField();
        $field->addFlags(new Required());
        $field->compile(static::createStub(DefinitionInstanceRegistry::class));

        $constraints = $this->serializer->buildConstraints($field);

        static::assertCount(3, $constraints);
        static::assertInstanceOf(NotBlank::class, $constraints[2]);
    }

    #[TestDox('throws exception when buildConstraints receives non-ElementFormatField')]
    public function testBuildConstraintsThrowsOnNonElementFormatField(): void
    {
        $invalidField = new JsonField('format', 'format');
        $invalidField->compile(static::createStub(DefinitionInstanceRegistry::class));

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(ElementFormatField::class, JsonField::class)
        );

        $this->serializer->buildConstraints($invalidField);
    }

    #[TestDox('validates breakpoint keys and rejects invalid ones')]
    public function testBreakpointKeyCallbackRejectsInvalidKeys(): void
    {
        $field = $this->createField();
        $constraints = $this->serializer->buildConstraints($field);
        $validator = Validation::createValidator();

        $violations = $validator->validate([
            'display' => ['xs' => true, 'invalid-bp' => false],
        ], $constraints);

        static::assertGreaterThan(0, $violations->count());
        static::assertStringContainsString('invalid-bp', (string) $violations);
    }

    private function createField(): ElementFormatField
    {
        $field = new ElementFormatField('format', 'format');
        $field->compile(static::createStub(DefinitionInstanceRegistry::class));

        return $field;
    }
}
