<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Cleanup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cleanup\CleanupCartTaskHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(CleanupCartTaskHandler::class)]
#[Package('checkout')]
class CleanupCartTaskHandlerTest extends TestCase
{
    #[TestDox('Delegates cart cleanup to persister with configured retention period')]
    public function testHandle(): void
    {
        $cartPersister = $this->createMock(AbstractCartPersister::class);
        $cartPersister->expects($this->once())
            ->method('prune')
            ->with(30);

        $handler = new CleanupCartTaskHandler(
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class),
            new MockClock(),
            $cartPersister,
            30
        );

        $handler->run();
    }
}
