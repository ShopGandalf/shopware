<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit\VersionCommitCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit\VersionCommitDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit\VersionCommitEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData\VersionCommitDataCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData\VersionCommitDataEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(VersionManager::class)]
class VersionManagerTest extends TestCase
{
    private VersionManager $versionManager;

    public function testCloneEntityWithFkAsExtension(): void
    {
        $entityReaderMock = $this->createMock(EntityReaderInterface::class);
        $serializer = $this->createMock(SerializerInterface::class);
        $entityWriterMock = $this->createMock(EntityWriterInterface::class);

        $this->versionManager = new VersionManager(
            $entityWriterMock,
            $entityReaderMock,
            $this->createMock(EntitySearcherInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $serializer,
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createMock(VersionCommitDefinition::class),
            $this->createMock(VersionCommitDataDefinition::class),
            $this->createMock(VersionDefinition::class),
            $this->createMock(LockFactory::class)
        );

        $entityCollectionMock = new EntityCollection([
            (new Entity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
        ]);

        $entityReaderMock->expects($this->once())->method('read')->willReturn($entityCollectionMock);
        $serializer->expects($this->once())->method('serialize')
            ->willReturn('{"extensions":{"foreignKeys":{"extensions":[],"apiAlias":null,"manyToOneId":"' . Uuid::randomHex() . '"}}}');

        $writeContextMock = $this->createMock(WriteContext::class);

        $writeContextMockWithVersionId = $this->createMock(WriteContext::class);
        $writeContextMock->expects($this->once())->method('createWithVersionId')->willReturn($writeContextMockWithVersionId);

        $entityWriterMock->expects($this->once())->method('insert')->willReturn([
            'product' => [
                new EntityWriteResult('1', ['languageId' => '1'], 'product', EntityWriteResult::OPERATION_INSERT),
            ],
        ]);

        $writeContextMockWithVersionId->expects($this->once())->method('scope')
            ->with(static::equalTo(Context::SYSTEM_SCOPE), static::callback(static function (callable $closure) use ($writeContextMockWithVersionId) {
                /** @var callable(MockObject&WriteContext): void $closure */
                $closure($writeContextMockWithVersionId);

                return true;
            }));

        $writeContextMockWithVersionId->expects($this->exactly(2))->method('getContext')->willReturn(Context::createDefaultContext());

        $registry = new StaticDefinitionInstanceRegistry(
            [
                VersionManagerTestDefinition::class,
                VersionManagerTestManufacturerDefinition::class,
                VersionDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $entityWriteResult = $this->versionManager->clone(
            $registry->getByEntityName('product'),
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            $writeContextMock,
            $this->createMock(CloneBehavior::class)
        );

        static::assertNotEmpty($entityWriteResult);
        static::assertSame('insert', $entityWriteResult['product'][0]->getOperation());
        static::assertSame('product', $entityWriteResult['product'][0]->getEntityName());
    }

    public function testCloneEntityNotExist(): void
    {
        $entityReaderMock = $this->createMock(EntityReaderInterface::class);
        $entityReaderMock->expects($this->once())->method('read')->willReturn(new EntityCollection([]));

        $this->versionManager = new VersionManager(
            $this->createMock(EntityWriterInterface::class),
            $entityReaderMock,
            $this->createMock(EntitySearcherInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(SerializerInterface::class),
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createMock(VersionCommitDefinition::class),
            $this->createMock(VersionCommitDataDefinition::class),
            $this->createMock(VersionDefinition::class),
            $this->createMock(LockFactory::class)
        );

        $productId = 'product-id';
        static::expectException(DataAbstractionLayerException::class);
        static::expectExceptionMessage(DataAbstractionLayerException::cannotCreateNewVersion('product', $productId)->getMessage());

        $registry = new StaticDefinitionInstanceRegistry(
            [
                VersionManagerTestDefinition::class,
                VersionManagerTestManufacturerDefinition::class,
                VersionDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $this->versionManager->clone(
            $registry->getByEntityName('product'),
            $productId,
            Uuid::randomHex(),
            Uuid::randomHex(),
            $this->createMock(WriteContext::class),
            $this->createMock(CloneBehavior::class)
        );
    }

    public function testMergeEntityWithLockedVersion(): void
    {
        $lockFactory = $this->createMock(LockFactory::class);

        $registry = new StaticDefinitionInstanceRegistry(
            [],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(false);
        $lockFactory->expects($this->once())->method('createLock')->willReturn($lock);

        $this->versionManager = new VersionManager(
            $this->createMock(EntityWriterInterface::class),
            $this->createMock(EntityReaderInterface::class),
            $this->createMock(EntitySearcherInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(SerializerInterface::class),
            $registry,
            $this->createMock(VersionCommitDefinition::class),
            $this->createMock(VersionCommitDataDefinition::class),
            $this->createMock(VersionDefinition::class),
            $lockFactory
        );

        $versionId = 'version-id';
        static::expectException(DataAbstractionLayerException::class);
        static::expectExceptionMessage(DataAbstractionLayerException::versionMergeAlreadyLocked($versionId)->getMessage());

        $this->versionManager->merge(
            $versionId,
            $this->createMock(WriteContext::class)
        );
    }

    public function testMergeFailsForNonExistentVersion(): void
    {
        $lockFactory = $this->createMock(LockFactory::class);
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lockFactory->method('createLock')->willReturn($lock);

        $entitySearcherMock = $this->createMock(EntitySearcherInterface::class);

        $entitySearcherMock->method('search')->willReturn(
            new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext())
        );

        $versionManager = new VersionManager(
            $this->createMock(EntityWriterInterface::class),
            $this->createMock(EntityReaderInterface::class),
            $entitySearcherMock,
            $this->createMock(EntityWriteGatewayInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(SerializerInterface::class),
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createMock(VersionCommitDefinition::class),
            $this->createMock(VersionCommitDataDefinition::class),
            $this->createMock(VersionDefinition::class),
            $lockFactory
        );

        $versionId = 'non-existent-version-id';

        static::expectException(DataAbstractionLayerException::class);
        static::expectExceptionMessage(DataAbstractionLayerException::versionNotExists($versionId)->getMessage());

        $versionManager->merge($versionId, $this->createMock(WriteContext::class));
    }

    public function testMergeUsesWriteContextVersionAsTargetVersion(): void
    {
        $sourceVersionId = Uuid::randomHex();
        $targetVersionId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $commitId = Uuid::randomHex();

        $commitData = new VersionCommitDataEntity();
        $commitData->setId(Uuid::randomHex());
        $commitData->setVersionCommitId($commitId);
        $commitData->setEntityName('product');
        $commitData->setEntityId(['id' => $productId, 'versionId' => $sourceVersionId]);
        $commitData->setAction(EntityWriteResult::OPERATION_UPDATE);
        $commitData->setPayload(['id' => $productId, 'ean' => 'source-version', 'productManufacturerVersionId' => Defaults::LIVE_VERSION]);

        $commit = new VersionCommitEntity();
        $commit->setId($commitId);
        $commit->setData(new VersionCommitDataCollection([$commitData]));

        $entitySearcher = $this->createMock(EntitySearcherInterface::class);
        $entitySearcher->expects($this->exactly(2))->method('search')->willReturnOnConsecutiveCalls(
            IdSearchResult::fromIds([$sourceVersionId], new Criteria(), Context::createDefaultContext()),
            IdSearchResult::fromIds([$commitId], new Criteria(), Context::createDefaultContext())
        );

        $entityReader = $this->createMock(EntityReaderInterface::class);
        $entityReader->expects($this->once())->method('read')->willReturn(new VersionCommitCollection([$commit]));

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())->method('createLock')->with('sw-merge-version-' . $sourceVersionId)->willReturn($lock);

        $versionCommitDefinition = $this->createMock(VersionCommitDefinition::class);
        $versionDefinition = $this->createMock(VersionDefinition::class);

        $entityWriter = $this->createMock(EntityWriterInterface::class);
        $entityWriter->expects($this->once())->method('sync')
            ->with(
                static::callback(static function (array $operations) use ($productId, $targetVersionId): bool {
                    /** @var list<SyncOperation> $productOperations */
                    $productOperations = [];
                    foreach ($operations as $operation) {
                        static::assertInstanceOf(SyncOperation::class, $operation);

                        if ($operation->getEntity() === 'product') {
                            $productOperations[] = $operation;
                        }
                    }

                    static::assertCount(1, $productOperations);
                    static::assertSame('upsert', $productOperations[0]->getAction());
                    $expectedPayload = [[
                        'id' => $productId,
                        'ean' => 'source-version',
                        'productManufacturerVersionId' => Defaults::LIVE_VERSION,
                        'versionId' => $targetVersionId,
                    ]];

                    static::assertSame($expectedPayload, $productOperations[0]->getPayload());

                    return true;
                }),
                static::callback(static function (WriteContext $writeContext) use ($targetVersionId): bool {
                    static::assertSame($targetVersionId, $writeContext->getContext()->getVersionId());
                    static::assertTrue($writeContext->hasState(VersionManager::MERGE_SCOPE));

                    return true;
                })
            )
            ->willReturn(new WriteResult([], [], []));

        $entityWriter->expects($this->once())->method('insert')
            ->with(
                $versionCommitDefinition,
                static::callback(static function (array $payload) use ($targetVersionId): bool {
                    static::assertSame($targetVersionId, $payload[0]['versionId']);
                    static::assertSame($targetVersionId, $payload[0]['data'][0]['entityId']['versionId']);

                    $mergePayload = json_decode((string) $payload[0]['data'][0]['payload'], true, 512, \JSON_THROW_ON_ERROR);
                    static::assertIsArray($mergePayload);
                    static::assertSame($targetVersionId, $mergePayload['versionId']);
                    static::assertSame(Defaults::LIVE_VERSION, $mergePayload['productManufacturerVersionId']);

                    return true;
                }),
                static::isInstanceOf(WriteContext::class)
            )
            ->willReturn([]);
        $entityWriter->method('delete')->willReturn(new WriteResult([], [], []));

        $registry = new StaticDefinitionInstanceRegistry(
            [
                VersionManagerTestDefinition::class,
                VersionManagerTestManufacturerDefinition::class,
                VersionDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $versionManager = new VersionManager(
            $entityWriter,
            $entityReader,
            $entitySearcher,
            $this->createMock(EntityWriteGatewayInterface::class),
            $eventDispatcher,
            $this->createMock(SerializerInterface::class),
            $registry,
            $versionCommitDefinition,
            $this->createMock(VersionCommitDataDefinition::class),
            $versionDefinition,
            $lockFactory
        );

        $versionManager->merge(
            $sourceVersionId,
            WriteContext::createFromContext(Context::createDefaultContext()->createWithVersionId($targetVersionId))
        );
    }
}

/**
 * @internal
 */
class VersionManagerTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'product';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new VersionField(),
            new ReferenceVersionField(VersionManagerTestManufacturerDefinition::class, 'product_manufacturer_version_id'),
        ]);
    }
}

/**
 * @internal
 */
class VersionManagerTestManufacturerDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'product_manufacturer';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new VersionField(),
        ]);
    }
}
