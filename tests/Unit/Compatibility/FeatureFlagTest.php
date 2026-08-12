<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Compatibility;

use Mollie\Shopware\Component\Compatibility\FeatureFlag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;

#[CoversClass(FeatureFlag::class)]
final class FeatureFlagTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $server = [];

    /**
     * @var array<string, mixed>
     */
    private array $env = [];

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $this->env = $_ENV;

        Feature::resetRegisteredFeatures();

        unset($_SERVER['V6_7_0_0'], $_ENV['V6_7_0_0'], $_SERVER['v6_7_0_0'], $_ENV['v6_7_0_0'], $_SERVER['FEATURE_ALL'], $_ENV['FEATURE_ALL']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_ENV = $this->env;

        Feature::resetRegisteredFeatures();
    }

    /**
     * The original bug: an unregistered flag makes Feature::isActive() emit an "Unknown feature"
     * warning, which APP_DEBUG turns into an exception in the middle of class reflection.
     */
    public function testUnregisteredFlagDoesNotTriggerAWarning(): void
    {
        // Reproduces the reported setup: another plugin has already registered its own flags, so the
        // registry is non-empty but does not know v6.7.0.0 yet, and APP_ENV is not prod.
        $_SERVER['APP_ENV'] = 'dev';
        Feature::registerFeature('SOME_OTHER_PLUGIN_FLAG');

        set_error_handler(function (int $errno, string $message): bool {
            self::fail('Expected no warning, got: ' . $message);
        }, \E_USER_WARNING);

        try {
            $active = FeatureFlag::isActive('v6.7.0.0');
        } finally {
            restore_error_handler();
        }

        self::assertFalse($active);
    }

    public function testUnregisteredFlagIsReadFromTheEnvironment(): void
    {
        $_SERVER['V6_7_0_0'] = '1';

        self::assertTrue(FeatureFlag::isActive('v6.7.0.0'));
    }

    public function testUnregisteredFlagIsReadFromTheLowercaseEnvironmentVariable(): void
    {
        $_SERVER['v6_7_0_0'] = '1';

        self::assertTrue(FeatureFlag::isActive('v6.7.0.0'));
    }

    #[DataProvider('falsyEnvironmentValues')]
    public function testUnregisteredFlagIsInactiveForFalsyEnvironmentValues(string $value): void
    {
        $_SERVER['V6_7_0_0'] = $value;

        self::assertFalse(FeatureFlag::isActive('v6.7.0.0'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function falsyEnvironmentValues(): array
    {
        return [
            'zero' => ['0'],
            'false' => ['false'],
            'empty' => [''],
        ];
    }

    public function testUnregisteredMajorFlagIsActivatedByFeatureAllMajor(): void
    {
        $_SERVER['FEATURE_ALL'] = 'major';

        self::assertTrue(FeatureFlag::isActive('v6.7.0.0'));
    }

    /**
     * FEATURE_ALL without "major" only enables minor flags, so it must not switch the payment
     * handler API of a 6.6 shop.
     */
    public function testUnregisteredMajorFlagIsNotActivatedByPlainFeatureAll(): void
    {
        $_SERVER['FEATURE_ALL'] = '1';

        self::assertFalse(FeatureFlag::isActive('v6.7.0.0'));
    }

    public function testRegisteredFlagIsAnsweredByShopware(): void
    {
        Feature::registerFeature('v6.7.0.0', ['major' => true, 'default' => true]);

        self::assertTrue(FeatureFlag::isActive('v6.7.0.0'));
    }

    public function testRegisteredFlagIsInactiveWhenShopwareDefaultsItOff(): void
    {
        Feature::registerFeature('v6.7.0.0', ['major' => true, 'default' => false]);

        self::assertFalse(FeatureFlag::isActive('v6.7.0.0'));
    }
}
