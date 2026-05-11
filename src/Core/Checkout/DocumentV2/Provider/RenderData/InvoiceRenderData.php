<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Provider\RenderData;

use Shopware\Core\Checkout\DocumentV2\Config\CompanyInfo;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfig;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentDisplayOptions;
use Shopware\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
use Shopware\Core\Checkout\DocumentV2\Zugferd\TypeCode;
use Shopware\Core\Checkout\DocumentV2\Zugferd\View\LineItemView;
use Shopware\Core\Checkout\DocumentV2\Zugferd\View\TradePartyView;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
final readonly class InvoiceRenderData extends AbstractRenderData
{
    /**
     * @param array<string, string> $templatePaths
     * @param array<string, mixed> $custom
     * @param array<string, mixed> $legacyConfig
     * @param list<LineItemView> $lineItems
     */
    public function __construct(
        DocumentConfig $config,
        CompanyInfo $company,
        DocumentDisplayOptions $display,
        string $documentDate,
        string $documentNumber,
        ?string $documentComment,
        array $templatePaths,
        public TypeCode $typeCode,
        public string $buyerReference,
        public TradePartyView $buyer,
        public ?\DateTimeImmutable $deliveryDate,
        public array $lineItems,
        public bool $intraCommunityDelivery,
        array $custom = [],
        array $legacyConfig = [],
    ) {
        parent::__construct(
            $config,
            $company,
            $display,
            $documentDate,
            $documentNumber,
            $documentComment,
            $templatePaths,
            $custom,
            $legacyConfig,
        );
    }
}
