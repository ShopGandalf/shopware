<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheTaskHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(InvalidateCacheTaskHandler::class)]
class InvalidateCacheTaskHandlerTest extends TestCase
{
    #[TestDox('Invalidates expired cache entries')]
    public function testInvalidatesExpiredCache(): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->once())->method('invalidateExpired');

        $handler = new InvalidateCacheTaskHandler(
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class),
            new MockClock(),
            $cacheInvalidator
        );
        $handler->run();
    }

    #[TestDox('Handles invalidation errors gracefully')]
    public function testRunDoesCatchException(): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->once())
            ->method('invalidateExpired')
            ->willThrowException(new \Exception());

        $handler = new InvalidateCacheTaskHandler(
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class),
            new MockClock(),
            $cacheInvalidator
        );
        $handler->run();
    }
}
