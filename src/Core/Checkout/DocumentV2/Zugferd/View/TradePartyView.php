<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Zugferd\View;

use Shopware\Core\Checkout\DocumentV2\Config\DocumentCompanyInfo;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * Precomputed XRechnung view of a trade party (buyer, ship-to, …).
 *
 * Generic enough to model any `<ram:*TradeParty>` derived from an order entity; sellers are
 * sourced from {@see DocumentCompanyInfo} instead.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class TradePartyView
{
    public function __construct(
        public ?string $id,
        public string $name,
        public ?string $street,
        public ?string $zipcode,
        public ?string $city,
        public ?string $countryIso,
        public ?string $email,
    ) {
    }

    public static function buyerFromOrder(OrderEntity $order): self
    {
        $customer = $order->getOrderCustomer();
        $billing = $order->getBillingAddress()
            ?? $order->getAddresses()?->get($order->getBillingAddressId());

        $name = trim(($customer?->getFirstName() ?? '') . ' ' . ($customer?->getLastName() ?? ''));

        if ($customer?->getCompany()) {
            $name = trim($name . ' - ' . $customer->getCompany());
        }

        return new self(
            id: $customer?->getCustomerNumber(),
            name: $name,
            street: $billing?->getStreet(),
            zipcode: $billing?->getZipcode(),
            city: $billing?->getCity(),
            countryIso: $billing?->getCountry()?->getIso(),
            email: $customer?->getEmail(),
        );
    }
}
