<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Settings\Route;

use Mollie\Shopware\Component\Mollie\Mode;
use Mollie\Shopware\Component\Settings\Route\ConfigRoute;
use Mollie\Shopware\Component\Settings\Struct\ApiSettings;
use Mollie\Shopware\Component\Settings\Struct\CreditCardSettings;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Unit\Fake\FakeLanguageRepository;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;

#[CoversClass(ConfigRoute::class)]
final class ConfigRouteTest extends TestCase
{
    private FakeGateway $gateway;

    protected function setUp(): void
    {
        $this->gateway = new FakeGateway();
    }

    public function testTheConfiguredProfileIdIsAnsweredWithoutAskingMollie(): void
    {
        $route = $this->buildRoute(apiSettings: new ApiSettings('test_key', 'live_key', Mode::TEST, 'pfl_configured'));

        $response = $route->getConfig(new FakeSalesChannelContext());

        self::assertSame('pfl_configured', $response->getObject()->all()['profileId']);
        self::assertSame(0, $this->gateway->getCallCount('getCurrentProfile'));
    }

    public function testTheProfileIdIsReadFromMollieWhenItIsNotConfigured(): void
    {
        $route = $this->buildRoute(apiSettings: new ApiSettings('test_key', 'live_key', Mode::TEST, ''));

        $response = $route->getConfig(new FakeSalesChannelContext());

        self::assertSame('fake_profile', $response->getObject()->all()['profileId']);
    }

    public function testTheTestModeAndOneClickFlagAreAnswered(): void
    {
        $route = $this->buildRoute(
            apiSettings: new ApiSettings('test_key', 'live_key', Mode::TEST, 'pfl_configured'),
            paymentSettings: new PaymentSettings('', 0, oneClickPayment: true),
        );

        $payload = $route->getConfig(new FakeSalesChannelContext())->getObject()->all();

        self::assertTrue($payload['testMode']);
        self::assertTrue($payload['oneClickPayments']);
    }

    /**
     * A headless frontend cannot tell that its card token would be dropped unless the route answers
     * this, so the wiring to the credit card settings matters, not only the response struct.
     */
    public function testTheCreditCardComponentsFlagIsAnswered(): void
    {
        $route = $this->buildRoute(creditCardSettings: new CreditCardSettings(true));

        $payload = $route->getConfig(new FakeSalesChannelContext())->getObject()->all();

        self::assertTrue($payload['creditCardComponents']);
    }

    public function testTheCreditCardComponentsFlagIsAnsweredWhenTheyAreDisabled(): void
    {
        $route = $this->buildRoute(creditCardSettings: new CreditCardSettings(false));

        $payload = $route->getConfig(new FakeSalesChannelContext())->getObject()->all();

        self::assertFalse($payload['creditCardComponents']);
    }

    public function testTheLocaleComesFromTheSalesChannelLanguage(): void
    {
        $route = $this->buildRoute(languageRepository: new FakeLanguageRepository('nl-NL'));

        $payload = $route->getConfig(new FakeSalesChannelContext())->getObject()->all();

        self::assertSame('nl_NL', $payload['locale']);
    }

    public function testTheLanguageOfTheSignedInCustomerWins(): void
    {
        // A signed-in customer may browse in another language than the sales channel default.
        $languageRepository = new FakeLanguageRepository('de-DE');
        $route = $this->buildRoute(languageRepository: $languageRepository);

        $customer = new CustomerEntity();
        $customer->setId('customer-id');
        $customer->setLanguageId('customer-language-id');

        $context = new FakeSalesChannelContext();
        $context->setCustomer($customer);

        $payload = $route->getConfig($context)->getObject()->all();

        self::assertSame('de_DE', $payload['locale']);
        self::assertSame(['customer-language-id'], $languageRepository->getRequestedIds());
    }

    public function testAnUnknownLanguageFallsBackToEnglish(): void
    {
        $route = $this->buildRoute(languageRepository: new FakeLanguageRepository());

        $payload = $route->getConfig(new FakeSalesChannelContext())->getObject()->all();

        self::assertSame('en_GB', $payload['locale']);
    }

    public function testALocaleMollieDoesNotKnowFallsBackToTheSameRegion(): void
    {
        $route = $this->buildRoute(languageRepository: new FakeLanguageRepository('gsw-CH'));

        $payload = $route->getConfig(new FakeSalesChannelContext())->getObject()->all();

        self::assertSame('de_CH', $payload['locale']);
    }

    private function buildRoute(
        ?ApiSettings $apiSettings = null,
        ?PaymentSettings $paymentSettings = null,
        ?FakeLanguageRepository $languageRepository = null,
        ?CreditCardSettings $creditCardSettings = null,
    ): ConfigRoute {
        $settings = new FakeSettingsService(
            paymentSettings: $paymentSettings,
            apiSettings: $apiSettings ?? new ApiSettings('test_key', 'live_key', Mode::TEST, 'pfl_configured'),
            creditCardSettings: $creditCardSettings,
        );

        return new ConfigRoute($settings, $this->gateway, $languageRepository ?? new FakeLanguageRepository('en-GB'));
    }
}
