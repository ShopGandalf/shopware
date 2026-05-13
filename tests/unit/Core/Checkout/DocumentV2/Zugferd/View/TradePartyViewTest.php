<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Zugferd\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Zugferd\View\TradePartyView;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(TradePartyView::class)]
class TradePartyViewTest extends TestCase
{
    public function testBuyerFromOrderComposesFromCustomerAndBillingAddress(): void
    {
        $order = $this->orderWithBillingAddress(
            firstName: 'Max',
            lastName: 'Mustermann',
            company: null,
            customerNumber: '1337',
            email: 'max@example.com',
            street: 'Ebbinghoff 10',
            zipcode: '48624',
            city: 'Schöppingen',
            countryIso: 'DE',
        );

        $view = TradePartyView::buyerFromOrder($order);

        static::assertSame('1337', $view->id);
        static::assertSame('Max Mustermann', $view->name);
        static::assertSame('Ebbinghoff 10', $view->street);
        static::assertSame('48624', $view->zipcode);
        static::assertSame('Schöppingen', $view->city);
        static::assertSame('DE', $view->countryIso);
        static::assertSame('max@example.com', $view->email);
    }

    public function testBuyerNameAppendsCompanyWhenPresent(): void
    {
        $order = $this->orderWithBillingAddress(
            firstName: 'Jane',
            lastName: 'Doe',
            company: 'Acme GmbH',
            street: '',
            zipcode: '',
            city: '',
        );

        $view = TradePartyView::buyerFromOrder($order);

        static::assertSame('Jane Doe - Acme GmbH', $view->name);
    }

    public function testBuyerFallsBackToAddressesCollectionWhenBillingAddressNotResolved(): void
    {
        $billingId = Uuid::randomHex();

        $address = new OrderAddressEntity();
        $address->setUniqueIdentifier($billingId);
        $address->setId($billingId);
        $address->setStreet('Fallback Street 1');
        $address->setZipcode('11111');
        $address->setCity('Fallback');

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setBillingAddressId($billingId);
        $order->setOrderCustomer($this->customer('A', 'B'));
        $order->setAddresses(new OrderAddressCollection([$address]));

        $view = TradePartyView::buyerFromOrder($order);

        static::assertSame('Fallback Street 1', $view->street);
        static::assertSame('11111', $view->zipcode);
        static::assertSame('Fallback', $view->city);
    }

    private function orderWithBillingAddress(
        string $firstName = '',
        string $lastName = '',
        ?string $company = null,
        string $customerNumber = '',
        string $email = '',
        ?string $street = null,
        ?string $zipcode = null,
        ?string $city = null,
        ?string $countryIso = null,
    ): OrderEntity {
        $address = new OrderAddressEntity();
        $address->setUniqueIdentifier(Uuid::randomHex());

        if ($street !== null) {
            $address->setStreet($street);
        }

        if ($zipcode !== null) {
            $address->setZipcode($zipcode);
        }

        if ($city !== null) {
            $address->setCity($city);
        }

        if ($countryIso !== null) {
            $country = new CountryEntity();
            $country->setIso($countryIso);
            $address->setCountry($country);
        }

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setBillingAddress($address);
        $order->setOrderCustomer($this->customer($firstName, $lastName, $company, $customerNumber, $email));

        return $order;
    }

    private function customer(
        string $firstName,
        string $lastName,
        ?string $company = null,
        string $customerNumber = '',
        string $email = '',
    ): OrderCustomerEntity {
        $customer = new OrderCustomerEntity();
        $customer->setUniqueIdentifier(Uuid::randomHex());
        $customer->setFirstName($firstName);
        $customer->setLastName($lastName);
        $customer->setCustomerNumber($customerNumber);
        $customer->setEmail($email);

        if ($company !== null) {
            $customer->setCompany($company);
        }

        return $customer;
    }
}
