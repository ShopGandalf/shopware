<?php declare(strict_types=1);

namespace Shopware\Core\Framework\RateLimiter\Exception;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class RateLimitExceededException extends ShopwareHttpException
{
    private readonly int $now;

    public function __construct(
        private readonly int $retryAfter,
        ClockInterface $clock,
        ?\Throwable $e = null,
    ) {
        $this->now = $clock->now()->getTimestamp();

        parent::__construct(
            'Too many requests, try again in {{ seconds }} seconds.',
            ['seconds' => $this->getWaitTime()],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__RATE_LIMIT_EXCEEDED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_TOO_MANY_REQUESTS;
    }

    public function getWaitTime(): int
    {
        return $this->retryAfter - $this->now;
    }
}
