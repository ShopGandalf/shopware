<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Exception\InvalidImitateCustomerTokenException;
use Shopware\Core\Checkout\Customer\ImitateCustomerTokenGenerator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ImitateCustomerTokenGenerator::class)]
class ImitateCustomerTokenGeneratorTest extends TestCase
{
    private const SALES_CHANNEL_ID = '0146543d6a6241718da05d5ee6f6891a';
    private const CUSTOMER_ID = 'bcf76884cb764eb2b9650bb2fcf1073e';
    private const USER_ID = 'bcf76884cb764eb2b9650bb2fcf1073f';
    private const APP_SECRET = 'testAppSecret';

    private MockClock $clock;

    private ImitateCustomerTokenGenerator $imitateCustomerTokenGenerator;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2025-01-01 12:00:00');
        $this->imitateCustomerTokenGenerator = new ImitateCustomerTokenGenerator(self::APP_SECRET, $this->clock);
    }

    #[DataProvider('validTokenProvider')]
    #[TestDox('Token remains valid before lifetime is exceeded')]
    public function testTokenRemainsValid(int $secondsBeforeExpiry): void
    {
        $token = $this->imitateCustomerTokenGenerator->generate(self::SALES_CHANNEL_ID, self::CUSTOMER_ID, self::USER_ID);

        $this->clock->modify(\sprintf('+%d seconds', ImitateCustomerTokenGenerator::TOKEN_LIFETIME - $secondsBeforeExpiry));

        $this->expectNotToPerformAssertions();
        $this->imitateCustomerTokenGenerator->validate($token, self::SALES_CHANNEL_ID, self::CUSTOMER_ID, self::USER_ID);
    }

    #[TestDox('Token expires after lifetime is exceeded')]
    public function testTokenExpires(): void
    {
        $token = $this->imitateCustomerTokenGenerator->generate(self::SALES_CHANNEL_ID, self::CUSTOMER_ID, self::USER_ID);

        $this->clock->modify(\sprintf('+%d seconds', ImitateCustomerTokenGenerator::TOKEN_LIFETIME + 1));

        $this->expectException(InvalidImitateCustomerTokenException::class);
        $this->imitateCustomerTokenGenerator->validate($token, self::SALES_CHANNEL_ID, self::CUSTOMER_ID, self::USER_ID);
    }

    #[TestDox('Invalid token format throws InvalidImitateCustomerTokenException')]
    public function testInvalidTokenFormat(): void
    {
        $this->expectException(InvalidImitateCustomerTokenException::class);

        $this->imitateCustomerTokenGenerator->validate('invalidToken', self::SALES_CHANNEL_ID, self::CUSTOMER_ID, self::USER_ID);
    }

    /**
     * @return \Generator<string, array{int}>
     */
    public static function validTokenProvider(): \Generator
    {
        yield 'valid before expiry' => [1];
        yield 'valid at exact boundary' => [0];
    }
}
