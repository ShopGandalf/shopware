<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart\Token;

use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Validation\Constraint;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\Cart\Token\JWTFactoryV2;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Checkout\Payment\Cart\Token\TestKey;
use Shopware\Core\Test\Stub\Checkout\Payment\Cart\Token\TestSigner;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(JWTFactoryV2::class)]
#[Package('checkout')]
class JWTFactoryV2Test extends TestCase
{
    private MockClock $clock;

    private JWTFactoryV2 $tokenFactory;

    protected function setUp(): void
    {
        $configuration = Configuration::forSymmetricSigner(new TestSigner(), new TestKey());
        $configuration = $configuration->withValidationConstraints(new NoopConstraint());
        $connection = $this->createMock(Connection::class);
        $this->clock = new MockClock('2025-01-01 12:00:00');
        $this->tokenFactory = new JWTFactoryV2($configuration, $connection, $this->clock);
    }

    #[TestDox('Token generation and parsing preserves transaction data')]
    public function testTokenPreservesTransactionData(): void
    {
        $transaction = self::createTransaction();
        $tokenStruct = new TokenStruct(
            paymentMethodId: $transaction->getPaymentMethodId(),
            transactionId: $transaction->getId(),
            expires: 3600,
            clock: $this->clock
        );

        $token = $this->tokenFactory->generateToken($tokenStruct);
        static::assertNotEmpty($token);

        $parsedToken = $this->tokenFactory->parseToken($token);

        static::assertSame($transaction->getId(), $parsedToken->getTransactionId());
        static::assertSame($transaction->getPaymentMethodId(), $parsedToken->getPaymentMethodId());
        static::assertSame($token, $parsedToken->getToken());
    }

    #[DataProvider('dataProviderExpiration')]
    #[TestDox('Token expiration calculated correctly with different expiration durations')]
    public function testTokenExpirationCalculation(int $expiration): void
    {
        $transaction = self::createTransaction();
        $tokenStruct = new TokenStruct(
            paymentMethodId: $transaction->getPaymentMethodId(),
            transactionId: $transaction->getId(),
            expires: $expiration,
            clock: $this->clock
        );

        $token = $this->tokenFactory->generateToken($tokenStruct);
        static::assertNotEmpty($token);

        $parsedToken = $this->tokenFactory->parseToken($token);

        $expectedExpiry = $this->clock->now()->getTimestamp() + $expiration;
        static::assertSame($expectedExpiry, $parsedToken->getExpires());
    }

    #[DataProvider('tokenExpiryProgressionProvider')]
    #[TestDox('Token expiry status after $secondsElapsed seconds matches expected state')]
    public function testTokenExpiryProgression(int $secondsElapsed, bool $expectedExpired): void
    {
        $transaction = self::createTransaction();
        $expirationSeconds = 1800;
        $tokenStruct = new TokenStruct(
            paymentMethodId: $transaction->getPaymentMethodId(),
            transactionId: $transaction->getId(),
            expires: $expirationSeconds,
            clock: $this->clock
        );

        $token = $this->tokenFactory->generateToken($tokenStruct);
        static::assertNotEmpty($token);

        $this->clock->modify(\sprintf('+%d seconds', $secondsElapsed));

        $parsedToken = $this->tokenFactory->parseToken($token);
        static::assertSame($expectedExpired, $parsedToken->isExpired());
    }

    #[TestDox('Token invalidation always returns false')]
    public function testInvalidateToken(): void
    {
        $token = Uuid::randomHex();
        static::assertNotEmpty($token);
        $success = $this->tokenFactory->invalidateToken($token);
        static::assertFalse($success);
    }

    #[TestDox('Invalid token format throws PaymentException')]
    public function testGetInvalidFormattedToken(): void
    {
        $token = Uuid::randomHex();

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('The provided token ' . $token . ' is invalid and the payment could not be processed.');

        static::assertNotEmpty($token);

        $this->tokenFactory->parseToken($token);
    }

    #[TestDox('Tampered token signature throws PaymentException')]
    public function testGetTokenWithInvalidSignature(): void
    {
        $transaction = self::createTransaction();
        $tokenStruct = new TokenStruct(null, null, $transaction->getPaymentMethodId(), $transaction->getId());
        $token = $this->tokenFactory->generateToken($tokenStruct);
        $invalidToken = substr($token, 0, -5);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('The provided token ' . $invalidToken . ' is invalid and the payment could not be processed.');

        static::assertNotEmpty($invalidToken);

        $this->tokenFactory->parseToken($invalidToken);
    }

    #[TestDox('Expired token fails strict validation')]
    public function testExpiredTokenWithStrictValidation(): void
    {
        $configuration = Configuration::forSymmetricSigner(new TestSigner(), new TestKey());
        $configuration = $configuration->withValidationConstraints(new StrictValidAt($this->clock));
        $tokenFactory = new JWTFactoryV2($configuration, $this->createMock(Connection::class), $this->clock);

        $transaction = self::createTransaction();
        $tokenStruct = new TokenStruct(
            paymentMethodId: $transaction->getPaymentMethodId(),
            transactionId: $transaction->getId(),
            expires: -50,
            clock: $this->clock
        );
        $token = $tokenFactory->generateToken($tokenStruct);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('The provided token ' . $token . ' is invalid and the payment could not be processed.');

        static::assertNotEmpty($token);

        $tokenFactory->parseToken($token);
    }

    #[TestDox('Token not found in database throws invalidated exception')]
    public function testTokenNotStored(): void
    {
        $configuration = Configuration::forSymmetricSigner(new TestSigner(), new TestKey());
        $configuration = $configuration->withValidationConstraints(new NoopConstraint());
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchOne')
            ->willReturn(false);

        $tokenFactory = new JWTFactoryV2($configuration, $connection);

        $transaction = self::createTransaction();
        $tokenStruct = new TokenStruct(null, null, $transaction->getPaymentMethodId(), $transaction->getId(), null, -50);
        $token = $tokenFactory->generateToken($tokenStruct);

        static::expectException(PaymentException::class);
        static::expectExceptionMessage('The provided token ' . $token . ' is invalidated and the payment could not be processed.');

        static::assertNotEmpty($token);

        $tokenFactory->parseToken($token);
    }

    public static function createTransaction(): OrderTransactionEntity
    {
        $transactionStruct = new OrderTransactionEntity();
        $transactionStruct->setId(Uuid::randomHex());
        $transactionStruct->setOrderId(Uuid::randomHex());
        $transactionStruct->setPaymentMethodId(Uuid::randomHex());
        $transactionStruct->setStateId(Uuid::randomHex());

        return $transactionStruct;
    }

    /**
     * @return iterable<array-key, array{int}>
     */
    public static function dataProviderExpiration(): iterable
    {
        yield 'positive expire' => [30];
        yield 'negative expire' => [-30];
        yield 'zero expire' => [0];
    }

    /**
     * @return \Generator<string, array{int, bool}>
     */
    public static function tokenExpiryProgressionProvider(): \Generator
    {
        yield 'valid before expiry' => [0, false];
        yield 'valid one second before expiry' => [1799, false];
        yield 'valid at exactly expiry time' => [1800, false];
        yield 'expired after expiry' => [1860, true];
    }
}

/**
 * @internal
 */
#[Package('checkout')]
class NoopConstraint implements Constraint
{
    public function assert(Token $token): void
    {
    }
}
