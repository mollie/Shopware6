<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Token;

use Mollie\Shopware\Component\Payment\Token\PaymentTokenRepository;
use Mollie\Shopware\Unit\Fake\FakeConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;

#[CoversClass(PaymentTokenRepository::class)]
final class PaymentTokenRepositoryTest extends TestCase
{
    private const TOKEN = 'a-shopware-finalize-token';

    /** @var array<string, mixed> */
    private array $server = [];

    /** @var array<string, mixed> */
    private array $env = [];

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $this->env = $_ENV;

        Feature::resetRegisteredFeatures();
        unset($_SERVER['V6_8_0_0'], $_ENV['V6_8_0_0'], $_SERVER['v6_8_0_0'], $_ENV['v6_8_0_0'], $_SERVER['FEATURE_ALL'], $_ENV['FEATURE_ALL']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_ENV = $this->env;

        Feature::resetRegisteredFeatures();
    }

    public function testTokenIsConsumedWhenItsRowIsGone(): void
    {
        $connection = new FakeConnection();

        $this->assertTrue((new PaymentTokenRepository($connection))->isConsumed(self::TOKEN));
    }

    public function testTokenIsStillValidWhileItsRowExists(): void
    {
        $connection = new FakeConnection();
        $connection->withSingleValue(1);

        $this->assertFalse((new PaymentTokenRepository($connection))->isConsumed(self::TOKEN));
    }

    public function testLookupUsesThePaymentTokenTable(): void
    {
        $connection = new FakeConnection();

        (new PaymentTokenRepository($connection))->isConsumed(self::TOKEN);

        $this->assertStringContainsString('FROM payment_token WHERE token = :token', $connection->getFetchedStatements()[0]);
    }

    public function testOnShopware68TheTokenIsNeverReportedAsConsumed(): void
    {
        $_SERVER['V6_8_0_0'] = '1';
        $connection = new FakeConnection();

        $consumed = (new PaymentTokenRepository($connection))->isConsumed(self::TOKEN);

        $this->assertFalse($consumed);
        $this->assertSame([], $connection->getFetchedStatements());
    }
}
