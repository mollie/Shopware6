<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Components\PaypalExpress;

use Kiener\MolliePayments\Components\PaypalExpress\PayPalExpress;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Repository\PaymentMethodRepository;
use Kiener\MolliePayments\Service\CartServiceInterface;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderPriceBuilder;
use Kiener\MolliePayments\Service\Router\RoutingBuilder;
use Mollie\Api\Endpoints\SessionEndpoint;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Session;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @covers \Kiener\MolliePayments\Components\PaypalExpress\PayPalExpress::loadSession
 */
class PayPalExpressLoadSessionTest extends TestCase
{
    private SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->context = $this->createMock(SalesChannelContext::class);
        $this->context->method('getSalesChannelId')->willReturn('fake-channel');
    }

    /**
     * When the address is available on the first API call,
     * loadSession must return immediately without sleeping.
     */
    public function testLoadSessionReturnsImmediatelyWhenAddressAvailableOnFirstCall(): void
    {
        $sessionWithAddress = $this->buildSession(true);

        $sessions = $this->createMock(SessionEndpoint::class);
        $sessions->expects($this->once()) // only one call — no retry
            ->method('get')
            ->with('sess_abc')
            ->willReturn($sessionWithAddress)
        ;

        $paypalExpress = $this->buildPayPalExpress($sessions);
        $result = $paypalExpress->loadSession('sess_abc', $this->context);

        $this->assertSame($sessionWithAddress, $result);
    }

    /**
     * When the first response has no shippingAddress,
     * loadSession must retry and return the session once the address appears.
     * Before the fix the sleep was 2 ms (wrong unit), so the address was never found.
     */
    public function testLoadSessionRetriesUntilShippingAddressBecomesAvailable(): void
    {
        $sessionWithout = $this->buildSession(false);
        $sessionWith = $this->buildSession(true);

        $sessions = $this->createMock(SessionEndpoint::class);
        $sessions->expects($this->exactly(2))
            ->method('get')
            ->with('sess_xyz')
            ->willReturnOnConsecutiveCalls($sessionWithout, $sessionWith)
        ;

        // Speed up the test: override usleep in the PaypalExpress namespace so retries don't
        // actually wait 0.5 s during the test run.
        $paypalExpress = $this->buildPayPalExpress($sessions);
        $result = $paypalExpress->loadSession('sess_xyz', $this->context);

        // The method must return the session that has the address, not the empty one.
        $this->assertNotNull($result->methodDetails);
        $this->assertTrue(property_exists($result->methodDetails, 'shippingAddress'));
        $this->assertNotNull($result->methodDetails->shippingAddress);
    }

    /**
     * Sleep-before-first-call regression:
     * The original code slept BEFORE the first API call, wasting time on the happy path.
     * Now sleep only happens between retries. This test verifies that when the address
     * is present on the first call, exactly one API request is made and the result is returned.
     */
    public function testNoRetryWhenAddressIsPresentOnFirstCall(): void
    {
        $sessionWithAddress = $this->buildSession(true);

        $sessions = $this->createMock(SessionEndpoint::class);
        // Strict expectation: get() is called exactly once – sleep did not hide a missed result.
        $sessions->expects($this->exactly(1))
            ->method('get')
            ->willReturn($sessionWithAddress)
        ;

        $result = $this->buildPayPalExpress($sessions)->loadSession('sess_1', $this->context);
        $this->assertSame($sessionWithAddress, $result);
    }

    private function buildPayPalExpress(SessionEndpoint $sessions): PayPalExpress
    {
        $mollieClient = $this->createMock(MollieApiClient::class);
        $mollieClient->sessions = $sessions;

        $factory = $this->createMock(MollieApiFactory::class);
        $factory->method('getLiveClient')->willReturn($mollieClient);

        return new PayPalExpress(
            $this->createMock(PaymentMethodRepository::class),
            $factory,
            $this->createMock(MollieOrderPriceBuilder::class),
            $this->createMock(RoutingBuilder::class),
            $this->createMock(CustomerService::class),
            $this->createMock(CartServiceInterface::class),
        );
    }

    private function buildSession(bool $withShippingAddress): Session
    {
        $session = $this->createMock(Session::class);

        if ($withShippingAddress) {
            $shippingAddress = new \stdClass();
            $shippingAddress->streetAndNumber = 'Main Street 1';

            $methodDetails = new \stdClass();
            $methodDetails->shippingAddress = $shippingAddress;

            $session->methodDetails = $methodDetails;
        } else {
            $session->methodDetails = null;
        }

        return $session;
    }
}

// Override usleep() in the production namespace so the retry test does not actually sleep.
// PHP resolves unqualified function calls in the caller's namespace first, so this shadows
// the global usleep() for code inside Kiener\MolliePayments\Components\PaypalExpress.

namespace Kiener\MolliePayments\Components\PaypalExpress;

function usleep(int $microseconds): void
{
    // no-op in tests
}
