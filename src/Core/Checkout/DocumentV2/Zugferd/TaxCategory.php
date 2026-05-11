<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Zugferd;

use Shopware\Core\Framework\Log\Package;

/**
 * UN/CEFACT codelist 5305 — Duty/tax/fee category code (subset used by XRechnung 3.0).
 *
 * @see https://unece.org/fileadmin/DAM/trade/untdid/d16b/tred/tred5305.htm
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
enum TaxCategory: string
{
    case STANDARD_RATE = 'S';
    case ZERO_RATED = 'Z';
}
