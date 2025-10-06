<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<NewsletterSubscribeResult>
 */
#[Package('after-sales')]
class NewsletterSubscribeRouteResponse extends StoreApiResponse
{
    public function __construct(string $status)
    {
        parent::__construct(new NewsletterSubscribeResult($status));
    }

    public function getResult(): NewsletterSubscribeResult
    {
        return $this->object;
    }
}
