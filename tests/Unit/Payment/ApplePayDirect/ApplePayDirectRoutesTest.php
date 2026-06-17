<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ApplePayDirect;

use Mollie\Shopware\Component\Payment\ApplePayDirect\ApplePayDirectException;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\ApplePayDirectEnabledResponse;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\ApplePayDirectEnabledRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\CreateSessionResponse;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\CreateSessionRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\GetApplePayIdResponse;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\GetApplePayIdRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\RestoreCartResponse;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\RestoreCartRoute;
use Mollie\Shopware\Component\Payment\ExpressMethod\VisibilityRestrictionCollection;
use Mollie\Shopware\Component\Settings\Struct\ApplePaySettings;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Payment\Fake\FakeApplePayGateway;
use Mollie\Shopware\Unit\Payment\Fake\FakeCartBackupService;
use Mollie\Shopware\Unit\Payment\Fake\FakePaymentMethodRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(ApplePayDirectEnabledRoute::class)]
#[CoversClass(GetApplePayIdRoute::class)]
#[CoversClass(CreateSessionRoute::class)]
#[CoversClass(RestoreCartRoute::class)]
#[CoversClass(RestoreCartResponse::class)]
final class ApplePayDirectRoutesTest extends TestCase
{
    private FakeSalesChannelContext $context;

    public function setUp(): void
    {
        $this->context = new FakeSalesChannelContext('sc-1', 'token-1');
    }

    // ------ ApplePayDirectEnabledRoute ------

    public function testEnabledRouteReturnsFalseWhenPaymentMethodNotFound(): void
    {
        $route = new ApplePayDirectEnabledRoute(
            new FakePaymentMethodRepository(null),
            new FakeSettingsService(),
        );

        $response = $route->getEnabled($this->context);

        $this->assertInstanceOf(ApplePayDirectEnabledResponse::class, $response);
        $this->assertFalse($response->isEnabled());
        $this->assertNull($response->getPaymentMethodId());
    }

    public function testEnabledRouteReturnsFalseWhenApplePayDisabled(): void
    {
        $route = new ApplePayDirectEnabledRoute(
            new FakePaymentMethodRepository('apple-pay-method-id'),
            new FakeSettingsService(applePaySettings: new ApplePaySettings(false, new VisibilityRestrictionCollection(), [])),
        );

        $response = $route->getEnabled($this->context);

        $this->assertInstanceOf(ApplePayDirectEnabledResponse::class, $response);
        $this->assertFalse($response->isEnabled());
        $this->assertSame('apple-pay-method-id', $response->getPaymentMethodId());
    }

    public function testEnabledRouteReturnsTrueWhenApplePayEnabled(): void
    {
        $route = new ApplePayDirectEnabledRoute(
            new FakePaymentMethodRepository('apple-pay-method-id'),
            new FakeSettingsService(applePaySettings: new ApplePaySettings(true, new VisibilityRestrictionCollection(), [])),
        );

        $response = $route->getEnabled($this->context);

        $this->assertInstanceOf(ApplePayDirectEnabledResponse::class, $response);
        $this->assertTrue($response->isEnabled());
        $this->assertSame('apple-pay-method-id', $response->getPaymentMethodId());
    }

    // ------ GetApplePayIdRoute ------

    public function testGetApplePayIdReturnsNullWhenNotFound(): void
    {
        $route = new GetApplePayIdRoute(
            new FakePaymentMethodRepository(null),
            new NullLogger(),
        );

        $response = $route->getId($this->context);

        $this->assertInstanceOf(GetApplePayIdResponse::class, $response);
        $this->assertNull($response->getId());
    }

    public function testGetApplePayIdReturnsIdWhenFound(): void
    {
        $route = new GetApplePayIdRoute(
            new FakePaymentMethodRepository('apple-pay-method-id'),
            new NullLogger(),
        );

        $response = $route->getId($this->context);

        $this->assertInstanceOf(GetApplePayIdResponse::class, $response);
        $this->assertSame('apple-pay-method-id', $response->getId());
    }

    // ------ CreateSessionRoute ------

    public function testCreateSessionThrowsWhenValidationUrlMissing(): void
    {
        $route = new CreateSessionRoute(
            new FakeApplePayGateway(),
            new FakeSettingsService(),
            new NullLogger(),
        );

        $request = new Request();

        try {
            $route->session($request, $this->context);
            $this->fail('Expected ApplePayDirectException was not thrown');
        } catch (ApplePayDirectException $exception) {
            $this->assertSame(ApplePayDirectException::INVALID_VALIDATION_URL, $exception->getErrorCode());
        }
    }

    public function testCreateSessionThrowsWhenGatewayFails(): void
    {
        $gateway = new FakeApplePayGateway();
        $gateway->setShouldThrow(true);

        $route = new CreateSessionRoute(
            $gateway,
            new FakeSettingsService(),
            new NullLogger(),
        );

        $request = new Request();
        $request->request->set('validationUrl', 'https://apple.com/validate');

        try {
            $route->session($request, $this->context);
            $this->fail('Expected ApplePayDirectException was not thrown');
        } catch (ApplePayDirectException $exception) {
            $this->assertSame(ApplePayDirectException::CREATE_SESSION_FAILED, $exception->getErrorCode());
        }
    }

    public function testCreateSessionIsSuccessful(): void
    {
        $route = new CreateSessionRoute(
            new FakeApplePayGateway(),
            new FakeSettingsService(),
            new NullLogger(),
        );

        $request = new Request();
        $request->request->set('validationUrl', 'https://apple.com/validate');

        $response = $route->session($request, $this->context);

        $this->assertInstanceOf(CreateSessionResponse::class, $response);
    }

    public function testCreateSessionThrowsWhenCustomDomainNotAllowed(): void
    {
        $route = new CreateSessionRoute(
            new FakeApplePayGateway(),
            new FakeSettingsService(applePaySettings: new ApplePaySettings(true, new VisibilityRestrictionCollection(), ['allowed.com'])),
            new NullLogger(),
        );

        $request = new Request();
        $request->request->set('validationUrl', 'https://apple.com/validate');
        $request->request->set('domain', 'notallowed.com');

        try {
            $route->session($request, $this->context);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (\InvalidArgumentException) {
            $this->assertTrue(true);
        }
    }

    // ------ RestoreCartRoute ------

    public function testRestoreCartIsSuccessful(): void
    {
        $route = new RestoreCartRoute(
            new FakeCartBackupService(),
            new NullLogger(),
        );

        $response = $route->restore($this->context);

        $this->assertInstanceOf(RestoreCartResponse::class, $response);
        $this->assertTrue((bool) $response->getObject()->get('success'));
    }

    public function testRestoreCartReturnsFalseWhenBackupServiceThrows(): void
    {
        $backup = new FakeCartBackupService();
        $backup->setShouldThrow(true);

        $route = new RestoreCartRoute(
            $backup,
            new NullLogger(),
        );

        $response = $route->restore($this->context);

        $this->assertInstanceOf(RestoreCartResponse::class, $response);
        $this->assertFalse((bool) $response->getObject()->get('success'));
    }
}
