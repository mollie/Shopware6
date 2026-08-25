<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Gateway;

use Mollie\Shopware\Component\Mollie\Exception\ApiException;
use Mollie\Shopware\Component\Mollie\Gateway\SessionGateway;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\SessionStatus;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClient;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClientFactory;
use Mollie\Shopware\Unit\Mollie\Fake\FakeRouteBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;

#[CoversClass(SessionGateway::class)]
final class SessionGatewayTest extends TestCase
{
    public function testAPaypalExpressSessionIsCreatedAtTheSessionEndpoint(): void
    {
        $client = new FakeClient(body: $this->sessionResponse());

        $this->gateway($client)->createPaypalExpressSession($this->cart(99.99), new FakeSalesChannelContext());

        $this->assertSame('sessions', $client->getLastUri());
    }

    public function testAPaypalExpressSessionAsksForTheExpressCheckoutFlow(): void
    {
        $client = new FakeClient(body: $this->sessionResponse());

        $this->gateway($client)->createPaypalExpressSession($this->cart(99.99), new FakeSalesChannelContext());

        $formParams = $client->getLastPostOptions()['form_params'];

        $this->assertSame(PaymentMethod::PAYPAL->value, $formParams['method']);
        $this->assertSame('express', $formParams['methodDetails']['checkoutFlow']);
    }

    public function testAPaypalExpressSessionIsOpenedForTheFullCartTotal(): void
    {
        $client = new FakeClient(body: $this->sessionResponse());

        $this->gateway($client)->createPaypalExpressSession($this->cart(99.99), new FakeSalesChannelContext());

        $this->assertSame(['value' => '99.99', 'currency' => 'EUR'], $client->getLastPostOptions()['form_params']['amount']);
    }

    public function testAPaypalExpressSessionCarriesTheReturnAndCancelUrlOfTheShop(): void
    {
        $client = new FakeClient(body: $this->sessionResponse());
        $routeBuilder = new FakeRouteBuilder(
            paypalExpressRedirectUrl: 'https://shop.test/paypal/return',
            paypalExpressCancelUrl: 'https://shop.test/paypal/cancel'
        );

        $this->gateway($client, $routeBuilder)->createPaypalExpressSession($this->cart(99.99), new FakeSalesChannelContext());

        $formParams = $client->getLastPostOptions()['form_params'];

        $this->assertSame('https://shop.test/paypal/return', $formParams['redirectUrl']);
        $this->assertSame('https://shop.test/paypal/cancel', $formParams['cancelUrl']);
    }

    public function testAFreshPaypalExpressSessionCarriesNoAuthenticationYet(): void
    {
        $client = new FakeClient(body: $this->sessionResponse(['authenticationId' => 'auth_1']));

        $session = $this->gateway($client)->createPaypalExpressSession($this->cart(99.99), new FakeSalesChannelContext());

        $this->assertSame('', $session->getAuthenticationId());
    }

    public function testASessionIsReadFromTheSessionEndpoint(): void
    {
        $client = new FakeClient(body: $this->sessionResponse());

        $this->gateway($client)->getSession('ses_1', new FakeSalesChannelContext());

        $this->assertSame('sessions/ses_1', $client->getLastUri());
    }

    public function testTheReadSessionCarriesIdAndStatus(): void
    {
        $client = new FakeClient(body: $this->sessionResponse(['status' => SessionStatus::COMPLETED->value]));

        $session = $this->gateway($client)->getSession('ses_1', new FakeSalesChannelContext());

        $this->assertSame('ses_1', $session->getId());
        $this->assertSame(SessionStatus::COMPLETED, $session->getStatus());
    }

    public function testThePaymentIdIsDerivedFromTheCheckoutReturnLink(): void
    {
        $client = new FakeClient(body: $this->sessionResponse([
            '_links' => ['redirect' => ['href' => 'https://www.mollie.com/checkout/return/abcdef']],
        ]));

        $session = $this->gateway($client)->getSession('ses_1', new FakeSalesChannelContext());

        $this->assertSame('tr_abcdef', $session->getPaymentId());
    }

    public function testASessionThatAlreadyCarriesAShippingAddressIsReturnedWithoutRetrying(): void
    {
        $client = new FakeClient(body: $this->sessionResponse([
            'shippingAddress' => [
                'givenName' => 'Erika',
                'familyName' => 'Mustermann',
                'email' => 'erika@example.com',
                'streetAndNumber' => 'Musterstr. 1',
                'postalCode' => '12345',
                'city' => 'Berlin',
                'country' => 'DE',
            ],
        ]));

        $session = $this->gateway($client)->loadSession('ses_1', new FakeSalesChannelContext());

        $this->assertSame('Erika', $session->getShippingAddress()?->getGivenName());
    }

    public function testCancellingASessionDeletesItAtMollie(): void
    {
        $client = new FakeClient(body: $this->sessionResponse(['status' => SessionStatus::EXPIRED->value]));

        $session = $this->gateway($client)->cancelSession('ses_1', new FakeSalesChannelContext());

        $this->assertSame('DELETE', $client->getLastMethod());
        $this->assertSame('sessions/ses_1', $client->getLastUri());
        $this->assertSame(SessionStatus::EXPIRED, $session->getStatus());
    }

    public function testAMollieErrorWhileReadingASessionBecomesAnApiException(): void
    {
        $this->expectException(ApiException::class);

        $this->gateway(new FakeClient())->getSession('ses_1', new FakeSalesChannelContext());
    }

    public function testAMollieErrorWhileOpeningAPaypalExpressSessionBecomesAnApiException(): void
    {
        $this->expectException(ApiException::class);

        $this->gateway(new FakeClient())->createPaypalExpressSession($this->cart(99.99), new FakeSalesChannelContext());
    }

    public function testAMollieErrorWhileCancellingASessionBecomesAnApiException(): void
    {
        $this->expectException(ApiException::class);

        $this->gateway(new FakeClient())->cancelSession('ses_1', new FakeSalesChannelContext());
    }

    private function gateway(FakeClient $client, ?FakeRouteBuilder $routeBuilder = null): SessionGateway
    {
        return new SessionGateway(
            new FakeClientFactory($client),
            $routeBuilder ?? new FakeRouteBuilder(),
            new FakeLogger()
        );
    }

    private function cart(float $totalPrice): Cart
    {
        $cart = new Cart('cart-token');
        $cart->setPrice(new CartPrice(
            $totalPrice,
            $totalPrice,
            $totalPrice,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_GROSS
        ));

        return $cart;
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function sessionResponse(array $overrides = []): array
    {
        return array_merge([
            'id' => 'ses_1',
            'status' => SessionStatus::OPEN->value,
            'method' => PaymentMethod::PAYPAL->value,
            'amount' => ['value' => '99.99', 'currency' => 'EUR'],
        ], $overrides);
    }
}
