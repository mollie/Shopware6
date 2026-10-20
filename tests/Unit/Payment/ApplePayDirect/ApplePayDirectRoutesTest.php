<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ApplePayDirect;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Mollie\Shopware\Component\Mollie\Exception\ApiException;
use Mollie\Shopware\Component\Payment\ApplePayDirect\ApplePayDirectException;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\ApplePayDirectEnabledResponse;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\ApplePayDirectEnabledRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\CreateSessionResponse;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\CreateSessionRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\GetApplePayIdResponse;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\GetApplePayIdRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\RestoreCartResponse;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\RestoreCartRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Service\ApplePayDomainVerificationService;
use Mollie\Shopware\Component\Payment\ExpressMethod\VisibilityRestrictionCollection;
use Mollie\Shopware\Component\Settings\Struct\ApplePaySettings;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Payment\Fake\FakeApplePayGateway;
use Mollie\Shopware\Unit\Payment\Fake\FakeCartBackupService;
use Mollie\Shopware\Unit\Payment\Fake\FakePaymentMethodRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
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
            $this->createDomainVerification($this->createFilesystemWithDomainFile()),
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
        $logger = new FakeLogger();

        $route = new CreateSessionRoute(
            $gateway,
            new FakeSettingsService(),
            $this->createDomainVerification($this->createFilesystemWithDomainFile()),
            $logger,
        );

        $request = new Request();
        $request->request->set('validationUrl', 'https://apple.com/validate');

        try {
            $route->session($request, $this->context);
            $this->fail('Expected ApplePayDirectException was not thrown');
        } catch (ApplePayDirectException $exception) {
            $this->assertSame(ApplePayDirectException::CREATE_SESSION_FAILED, $exception->getErrorCode());
            $this->assertFalse($logger->hasRecordThatContains(LogLevel::ERROR, 'mollie:applepay:download-verification'));
        }
    }

    public function testCreateSessionIsSuccessful(): void
    {
        $logger = new FakeLogger();
        $route = new CreateSessionRoute(
            new FakeApplePayGateway(),
            new FakeSettingsService(),
            $this->createDomainVerification($this->createFilesystemWithDomainFile()),
            $logger,
        );

        $request = new Request();
        $request->request->set('validationUrl', 'https://apple.com/validate');

        $response = $route->session($request, $this->context);

        $this->assertInstanceOf(CreateSessionResponse::class, $response);
        $this->assertFalse($logger->hasRecordThatContains(LogLevel::WARNING, 'domain verification file is missing'));
    }

    public function testCreateSessionThrowsWhenCustomDomainNotAllowed(): void
    {
        $route = new CreateSessionRoute(
            new FakeApplePayGateway(),
            new FakeSettingsService(applePaySettings: new ApplePaySettings(true, new VisibilityRestrictionCollection(), ['allowed.com'])),
            $this->createDomainVerification($this->createFilesystemWithDomainFile()),
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

    public function testCreateSessionDownloadsMissingDomainVerificationFile(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $route = new CreateSessionRoute(
            new FakeApplePayGateway(),
            new FakeSettingsService(),
            $this->createDomainVerification($filesystem, new GuzzleResponse(200, [], 'verification-content')),
            new NullLogger(),
        );

        $request = new Request();
        $request->request->set('validationUrl', 'https://apple.com/validate');

        $route->session($request, $this->context);

        $this->assertSame('verification-content', $filesystem->read(ApplePayDomainVerificationService::LOCAL_FILE));
    }

    #[DataProvider('failedDomainVerificationDownloadProvider')]
    public function testCreateSessionIsRequestedWhenDomainVerificationDownloadFails(GuzzleResponse|\Throwable $answer): void
    {
        $logger = new FakeLogger();
        $route = new CreateSessionRoute(
            new FakeApplePayGateway(),
            new FakeSettingsService(),
            $this->createDomainVerification(new Filesystem(new InMemoryFilesystemAdapter()), $answer),
            $logger,
        );

        $request = new Request();
        $request->request->set('validationUrl', 'https://apple.com/validate');

        $response = $route->session($request, $this->context);

        $this->assertInstanceOf(CreateSessionResponse::class, $response);
        $this->assertTrue($logger->hasRecordThatContains(LogLevel::WARNING, 'domain verification file is missing'));
    }

    /**
     * @return array<string, array{0: GuzzleResponse|\Throwable}>
     */
    public static function failedDomainVerificationDownloadProvider(): array
    {
        return [
            'mollie answers with an error status' => [new GuzzleResponse(500)],
            'mollie is not reachable' => [new ConnectException('Connection refused', new GuzzleRequest('GET', ApplePayDomainVerificationService::URL_FILE))],
        ];
    }

    public function testCreateSessionLogsHintWhenAppleCannotVerifyDomain(): void
    {
        $gateway = new FakeApplePayGateway();
        $gateway->setException(new ApiException(422, 'Unprocessable Entity', 'Apple is not able to verify your domain.', 'domain'));
        $logger = new FakeLogger();
        $route = new CreateSessionRoute(
            $gateway,
            new FakeSettingsService(),
            $this->createDomainVerification($this->createFilesystemWithDomainFile()),
            $logger,
        );

        $request = new Request();
        $request->request->set('validationUrl', 'https://apple.com/validate');

        try {
            $route->session($request, $this->context);
            $this->fail('Expected ApplePayDirectException was not thrown');
        } catch (ApplePayDirectException) {
            $this->assertTrue($logger->hasRecordThatContains(LogLevel::ERROR, 'mollie:applepay:download-verification'));
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

    private function createDomainVerification(Filesystem $filesystem, GuzzleResponse|\Throwable ...$answers): ApplePayDomainVerificationService
    {
        $httpClient = new Client(['handler' => HandlerStack::create(new MockHandler($answers))]);

        return new ApplePayDomainVerificationService($filesystem, $httpClient);
    }

    private function createFilesystemWithDomainFile(): Filesystem
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write(ApplePayDomainVerificationService::LOCAL_FILE, 'verification-content');

        return $filesystem;
    }
}
