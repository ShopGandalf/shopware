<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppStateService;
use Shopware\Core\Framework\App\Lifecycle\Persister\FlowEventPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\RuleConditionPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\ScriptPersister;
use Shopware\Core\Framework\App\Payment\PaymentMethodStateService;
use Shopware\Core\Framework\App\Template\TemplateStateService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Integration\IntegrationCollection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppStateService::class)]
class AppStateServiceTest extends TestCase
{
    public function testActivateAppUpdatesIntegrationWhenIntegrationIdIsSet(): void
    {
        $appId = Uuid::randomHex();
        $integrationId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $app = new AppEntity();
        $app->setUniqueIdentifier($appId);
        $app->setActive(false);
        $app->setIntegrationId($integrationId);

        $appCollection = new AppCollection([$app]);
        $searchResult = new EntitySearchResult('app', 1, $appCollection, null, new Criteria(), $context);

        $appRepo = $this->createMock(EntityRepository::class);
        $appRepo->method('search')->willReturn($searchResult);

        $integrationRepo = $this->createMock(EntityRepository::class);
        $integrationRepo->expects($this->once())
            ->method('update')
            ->with([['id' => $integrationId, 'deletedAt' => null]], $context);

        $this->makeService($appRepo, $integrationRepo)->activateApp($appId, $context);
    }

    public function testDeactivateAppUpdatesIntegrationWhenIntegrationIdIsSet(): void
    {
        $appId = Uuid::randomHex();
        $integrationId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $app = new AppEntity();
        $app->setUniqueIdentifier($appId);
        $app->setActive(true);
        $app->setIntegrationId($integrationId);
        $app->setAllowDisable(true);

        $appCollection = new AppCollection([$app]);
        $searchResult = new EntitySearchResult('app', 1, $appCollection, null, new Criteria(), $context);

        $appRepo = $this->createMock(EntityRepository::class);
        $appRepo->method('search')->willReturn($searchResult);

        $integrationRepo = $this->createMock(EntityRepository::class);
        $integrationRepo->expects($this->once())
            ->method('update')
            ->with(
                static::callback(
                    static fn (array $data): bool => $data[0]['id'] === $integrationId
                        && $data[0]['deletedAt'] instanceof \DateTimeImmutable,
                ),
                $context,
            );

        $this->makeService($appRepo, $integrationRepo)->deactivateApp($appId, $context, true);
    }

    /**
     * @param EntityRepository<AppCollection> $appRepo
     * @param EntityRepository<IntegrationCollection> $integrationRepo
     */
    private function makeService(EntityRepository $appRepo, EntityRepository $integrationRepo): AppStateService
    {
        return new AppStateService(
            $appRepo,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(ActiveAppsLoader::class),
            static::createStub(TemplateStateService::class),
            static::createStub(ScriptPersister::class),
            static::createStub(PaymentMethodStateService::class),
            static::createStub(ScriptExecutor::class),
            static::createStub(RuleConditionPersister::class),
            static::createStub(FlowEventPersister::class),
            $integrationRepo,
        );
    }
}
