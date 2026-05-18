<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Mandate;

use Mollie\Shopware\Component\Mollie\Mode;
use Mollie\Shopware\Component\Payment\Mandate\MandateException;
use Mollie\Shopware\Component\Payment\Mandate\Route\ListMandatesResponse;
use Mollie\Shopware\Component\Payment\Mandate\Route\ListMandatesRoute;
use Mollie\Shopware\Component\Payment\Mandate\Route\RevokeMandateResponse;
use Mollie\Shopware\Component\Payment\Mandate\Route\RevokeMandateRoute;
use Mollie\Shopware\Component\Payment\Mandate\Route\StoreMandateIdResponse;
use Mollie\Shopware\Component\Payment\Mandate\Route\StoreMandateIdRoute;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Entity\Customer\Customer as MollieCustomer;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Customer\CustomerEntity;

#[CoversClass(ListMandatesRoute::class)]
#[CoversClass(RevokeMandateRoute::class)]
#[CoversClass(StoreMandateIdRoute::class)]
#[CoversClass(StoreMandateIdResponse::class)]
final class MandateRoutesTest extends TestCase
{
    private FakeSalesChannelContext $context;

    public function setUp(): void
    {
        $this->context = new FakeSalesChannelContext('sc-1', 'token-1');
    }

    // ------ ListMandatesRoute ------

    public function testListMandatesReturnsEmptyWhenNoCustomer(): void
    {
        $route = new ListMandatesRoute(
            new FakeGateway(),
            new FakeSettingsService(),
            new NullLogger(),
        );

        $response = $route->list('cust-1', $this->context);

        $this->assertInstanceOf(ListMandatesResponse::class, $response);
        $this->assertSame(0, $response->getMandates()->count());
    }

    public function testListMandatesReturnsEmptyWhenNoMollieExtension(): void
    {
        $customer = $this->buildCustomer('cust-1');
        $this->context->setCustomer($customer);

        $route = new ListMandatesRoute(
            new FakeGateway(),
            new FakeSettingsService(),
            new NullLogger(),
        );

        $response = $route->list('cust-1', $this->context);

        $this->assertInstanceOf(ListMandatesResponse::class, $response);
        $this->assertSame(0, $response->getMandates()->count());
    }

    public function testListMandatesReturnsEmptyWhenOneClickDisabled(): void
    {
        $customer = $this->buildCustomerWithMollieExtension('cust-1', 'mollie-cust-id');
        $this->context->setCustomer($customer);

        $route = new ListMandatesRoute(
            new FakeGateway(),
            new FakeSettingsService(paymentSettings: new PaymentSettings('', 0, false)),
            new NullLogger(),
        );

        $response = $route->list('cust-1', $this->context);

        $this->assertInstanceOf(ListMandatesResponse::class, $response);
        $this->assertSame(0, $response->getMandates()->count());
    }

    public function testListMandatesReturnsEmptyWhenNoMollieCustomerId(): void
    {
        $customer = $this->buildCustomerWithMollieExtension('cust-1', null);
        $this->context->setCustomer($customer);

        $route = new ListMandatesRoute(
            new FakeGateway(),
            new FakeSettingsService(paymentSettings: new PaymentSettings('', 0, true)),
            new NullLogger(),
        );

        $response = $route->list('cust-1', $this->context);

        $this->assertInstanceOf(ListMandatesResponse::class, $response);
        $this->assertSame(0, $response->getMandates()->count());
    }

    public function testListMandatesIsSuccessful(): void
    {
        $customer = $this->buildCustomerWithMollieExtension('cust-1', 'mollie-cust-id');
        $this->context->setCustomer($customer);

        $route = new ListMandatesRoute(
            new FakeGateway(),
            new FakeSettingsService(paymentSettings: new PaymentSettings('', 0, true)),
            new NullLogger(),
        );

        $response = $route->list('cust-1', $this->context);

        $this->assertInstanceOf(ListMandatesResponse::class, $response);
        $this->assertGreaterThan(0, $response->getMandates()->count());
    }

    // ------ RevokeMandateRoute ------

    public function testRevokeMandateThrowsWhenOneClickDisabled(): void
    {
        $route = new RevokeMandateRoute(
            new FakeGateway(),
            new FakeSettingsService(paymentSettings: new PaymentSettings('', 0, false)),
            new NullLogger(),
        );

        try {
            $route->revoke('cust-1', 'mandate-1', $this->context);
            $this->fail('Expected MandateException was not thrown');
        } catch (MandateException $exception) {
            $this->assertSame(MandateException::ONE_CLICK_DISABLED, $exception->getErrorCode());
        }
    }

    public function testRevokeMandateThrowsWhenNoCustomer(): void
    {
        $route = new RevokeMandateRoute(
            new FakeGateway(),
            new FakeSettingsService(paymentSettings: new PaymentSettings('', 0, true)),
            new NullLogger(),
        );

        try {
            $route->revoke('cust-1', 'mandate-1', $this->context);
            $this->fail('Expected MandateException was not thrown');
        } catch (MandateException $exception) {
            $this->assertSame(MandateException::NO_CUSTOMER, $exception->getErrorCode());
        }
    }

    public function testRevokeMandateThrowsWhenNoMollieExtension(): void
    {
        $customer = $this->buildCustomer('cust-1');
        $this->context->setCustomer($customer);

        $route = new RevokeMandateRoute(
            new FakeGateway(),
            new FakeSettingsService(paymentSettings: new PaymentSettings('', 0, true)),
            new NullLogger(),
        );

        try {
            $route->revoke('cust-1', 'mandate-1', $this->context);
            $this->fail('Expected MandateException was not thrown');
        } catch (MandateException $exception) {
            $this->assertSame(MandateException::MISSING_MOLLIE_CUSTOMER_ID, $exception->getErrorCode());
        }
    }

    public function testRevokeMandateThrowsWhenNoCustomerIdForProfile(): void
    {
        $customer = $this->buildCustomerWithMollieExtension('cust-1', null);
        $this->context->setCustomer($customer);

        $route = new RevokeMandateRoute(
            new FakeGateway(),
            new FakeSettingsService(paymentSettings: new PaymentSettings('', 0, true)),
            new NullLogger(),
        );

        try {
            $route->revoke('cust-1', 'mandate-1', $this->context);
            $this->fail('Expected MandateException was not thrown');
        } catch (MandateException $exception) {
            $this->assertSame(MandateException::ONE_CLICK_DISABLED, $exception->getErrorCode());
        }
    }

    public function testRevokeMandateIsSuccessful(): void
    {
        $customer = $this->buildCustomerWithMollieExtension('cust-1', 'mollie-cust-id');
        $this->context->setCustomer($customer);

        $route = new RevokeMandateRoute(
            new FakeGateway(),
            new FakeSettingsService(paymentSettings: new PaymentSettings('', 0, true)),
            new NullLogger(),
        );

        $response = $route->revoke('cust-1', 'mandate-1', $this->context);

        $this->assertInstanceOf(RevokeMandateResponse::class, $response);
    }

    // ------ StoreMandateIdRoute ------

    public function testStoreMandateIdIsSuccessful(): void
    {
        $route = new StoreMandateIdRoute(new NullLogger());

        $response = $route->store('cust-1', 'mandate-1', $this->context);

        $this->assertInstanceOf(StoreMandateIdResponse::class, $response);
    }

    private function buildCustomer(string $id): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId($id);
        $customer->setCustomerNumber($id);

        return $customer;
    }

    private function buildCustomerWithMollieExtension(string $id, ?string $mollieCustomerId): CustomerEntity
    {
        $customer = $this->buildCustomer($id);

        $extension = new MollieCustomer();
        if ($mollieCustomerId !== null) {
            $extension->setCustomerId('', Mode::TEST, $mollieCustomerId);
        }
        $customer->addExtension(Mollie::EXTENSION, $extension);

        return $customer;
    }
}
