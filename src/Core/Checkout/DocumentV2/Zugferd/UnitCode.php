<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Zugferd;

use Shopware\Core\Framework\Log\Package;

/**
 * UN/ECE Recommendation 20 unit-of-measure code (subset used by XRechnung 3.0).
 *
 * @see https://docs.peppol.eu/pracc/catalogue/1.0/codelist/UNECERec20/
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
enum UnitCode: string
{
    case PIECE = 'H87';
}
