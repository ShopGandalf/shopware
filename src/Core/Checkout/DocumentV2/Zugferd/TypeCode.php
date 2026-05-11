<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Zugferd;

use Shopware\Core\Framework\Log\Package;

/**
 * UN/CEFACT codelist 1001 — Document name code (subset used by Shopware).
 *
 * @see https://service.unece.org/trade/uncefact/vocabulary/uncl1001/
 *
 * @codeCoverageIgnore
 *
 * @internal
 */
#[Package('after-sales')]
enum TypeCode: int
{
    case INVOICE = 380;
    case CREDIT_NOTE = 381;
    case CANCELLATION_INVOICE = 384;
}
