<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Webhook\ScheduledTask\CleanupWebhookEventLogTaskHandler;
use Shopware\Core\Framework\Webhook\Service\WebhookCleanup;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(CleanupWebhookEventLogTaskHandler::class)]
class CleanupWebhookEventLogTaskHandlerTest extends TestCase
{
    #[TestDox('Delegates webhook log cleanup to cleanup service')]
    public function testHandler(): void
    {
        $cleaner = $this->createMock(WebhookCleanup::class);

        $cleaner->expects($this->once())->method('removeOldLogs');

        $handler = new CleanupWebhookEventLogTaskHandler(
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class),
            new MockClock(),
            $cleaner
        );

        $handler->run();
    }
}
