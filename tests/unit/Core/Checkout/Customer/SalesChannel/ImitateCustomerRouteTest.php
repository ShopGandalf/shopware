<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\ImitateCustomerTokenGenerator;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Customer\SalesChannel\ImitateCustomerRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\LogoutRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Clock\MockClock;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ImitateCustomerRoute::class)]
class ImitateCustomerRouteTest extends TestCase
{
    #[TestDox('Successful customer imitation returns new authentication token')]
    public function testImitateCustomer(): void
    {
        $customerId = Uuid::randomHex();
        $userId = Uuid::randomHex();

        $clock = new MockClock('2025-01-01 12:00:00');
        $imitateCustomerTokenGenerator = new ImitateCustomerTokenGenerator('testAppSecret', $clock);

        $token = $imitateCustomerTokenGenerator->generate(
            TestDefaults::SALES_CHANNEL,
            $customerId,
            $userId
        );

        $accountService = $this->createMock(AccountService::class);
        $accountService->method('loginById')->willReturn('newToken');

        $route = new ImitateCustomerRoute(
            $accountService,
            $imitateCustomerTokenGenerator,
            $this->createMock(LogoutRoute::class),
            $this->createMock(SalesChannelContextFactory::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(DataValidator::class),
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);

        $dataBag = new RequestDataBag([
            ImitateCustomerRoute::TOKEN => $token,
            ImitateCustomerRoute::CUSTOMER_ID => $customerId,
            ImitateCustomerRoute::USER_ID => $userId,
        ]);

        $response = $route->imitateCustomerLogin($dataBag, $salesChannelContext);

        static::assertSame('newToken', $response->getToken());
    }

    #[TestDox('Existing customer is logged out before impersonation')]
    public function testExistingCustomerIsLoggedOutBeforeImpersonation(): void
    {
        $customerId = Uuid::randomHex();
        $userId = Uuid::randomHex();
        $existingCustomerId = Uuid::randomHex();

        $clock = new MockClock('2025-01-01 12:00:00');
        $imitateCustomerTokenGenerator = new ImitateCustomerTokenGenerator('testAppSecret', $clock);

        $token = $imitateCustomerTokenGenerator->generate(
            TestDefaults::SALES_CHANNEL,
            $customerId,
            $userId
        );

        $existingCustomer = new CustomerEntity();

        $logoutRoute = $this->createMock(LogoutRoute::class);
        $logoutToken = 'logout-token';
        $logoutRoute->expects($this->once())
            ->method('logout')
            ->willReturn(new ContextTokenResponse($logoutToken));

        $newContext = $this->createMock(SalesChannelContext::class);
        $newContext->expects($this->once())
            ->method('setImitatingUserId')
            ->with($userId);

        $contextFactory = $this->createMock(SalesChannelContextFactory::class);
        $contextFactory->expects($this->once())
            ->method('create')
            ->with($logoutToken, TestDefaults::SALES_CHANNEL)
            ->willReturn($newContext);

        $accountService = $this->createMock(AccountService::class);
        $accountService->expects($this->once())
            ->method('loginById')
            ->with($customerId, $newContext)
            ->willReturn('final-token');

        $route = new ImitateCustomerRoute(
            $accountService,
            $imitateCustomerTokenGenerator,
            $logoutRoute,
            $contextFactory,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(DataValidator::class),
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);
        $salesChannelContext->method('getCustomer')->willReturn($existingCustomer);
        $salesChannelContext->method('getCustomerId')->willReturn($existingCustomerId);

        $salesChannelContext->expects($this->once())
            ->method('setImitatingUserId')
            ->with($userId);

        $dataBag = new RequestDataBag([
            ImitateCustomerRoute::TOKEN => $token,
            ImitateCustomerRoute::CUSTOMER_ID => $customerId,
            ImitateCustomerRoute::USER_ID => $userId,
        ]);

        $response = $route->imitateCustomerLogin($dataBag, $salesChannelContext);

        static::assertSame('final-token', $response->getToken());
    }

    #[TestDox('Imitating user ID is set on context')]
    public function testImitatingUserIdIsSetOnContext(): void
    {
        $customerId = Uuid::randomHex();
        $userId = Uuid::randomHex();

        $clock = new MockClock('2025-01-01 12:00:00');
        $imitateCustomerTokenGenerator = new ImitateCustomerTokenGenerator('testAppSecret', $clock);

        $token = $imitateCustomerTokenGenerator->generate(
            TestDefaults::SALES_CHANNEL,
            $customerId,
            $userId
        );

        $accountService = $this->createMock(AccountService::class);
        $accountService->method('loginById')->willReturn('newToken');

        $route = new ImitateCustomerRoute(
            $accountService,
            $imitateCustomerTokenGenerator,
            $this->createMock(LogoutRoute::class),
            $this->createMock(SalesChannelContextFactory::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(DataValidator::class),
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);
        $salesChannelContext->method('getCustomer')->willReturn(null);
        $salesChannelContext->method('getCustomerId')->willReturn(null);

        $salesChannelContext->expects($this->once())
            ->method('setImitatingUserId')
            ->with($userId);

        $dataBag = new RequestDataBag([
            ImitateCustomerRoute::TOKEN => $token,
            ImitateCustomerRoute::CUSTOMER_ID => $customerId,
            ImitateCustomerRoute::USER_ID => $userId,
        ]);

        $response = $route->imitateCustomerLogin($dataBag, $salesChannelContext);

        static::assertSame('newToken', $response->getToken());
    }
}
