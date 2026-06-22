<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\MethodRemover;

use Mollie\Shopware\Component\Payment\MethodRemover\BusinessCustomerPaymentMethodRemover;
use Mollie\Shopware\Component\Payment\PaymentHandlerLocator;
use Mollie\Shopware\Unit\Builder\CustomerBuilder;
use Mollie\Shopware\Unit\Builder\PaymentMethodBuilder;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Payment\Fake\FakeBusinessCustomerAwarePaymentHandler;
use Mollie\Shopware\Unit\Payment\Fake\FakePaymentMethodHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;

#[CoversClass(BusinessCustomerPaymentMethodRemover::class)]
final class BusinessCustomerPaymentMethodRemoverTest extends TestCase
{
    public function testRemovesBusinessMethodForPrivateCustomer(): void
    {
        $context = new FakeSalesChannelContext();
        $context->setCustomer($this->buildCustomer(''));

        $result = $this->getRemover()->remove($this->buildPaymentMethods(), '', $context);

        $this->assertCount(1, $result);
        $this->assertNull($result->get('business-id'));
        $this->assertNotNull($result->get('regular-id'));
    }

    public function testKeepsBusinessMethodForBusinessCustomer(): void
    {
        $context = new FakeSalesChannelContext();
        $context->setCustomer($this->buildCustomer('ACME Inc.'));

        $result = $this->getRemover()->remove($this->buildPaymentMethods(), '', $context);

        $this->assertCount(2, $result);
        $this->assertNotNull($result->get('business-id'));
        $this->assertNotNull($result->get('regular-id'));
    }

    public function testRemovesBusinessMethodWhenNoCustomer(): void
    {
        $context = new FakeSalesChannelContext();

        $result = $this->getRemover()->remove($this->buildPaymentMethods(), '', $context);

        $this->assertCount(1, $result);
        $this->assertNull($result->get('business-id'));
    }

    private function getRemover(): BusinessCustomerPaymentMethodRemover
    {
        $handlerLocator = new PaymentHandlerLocator([
            new FakeBusinessCustomerAwarePaymentHandler(),
            new FakePaymentMethodHandler(),
        ]);

        return new BusinessCustomerPaymentMethodRemover($handlerLocator);
    }

    private function buildPaymentMethods(): PaymentMethodCollection
    {
        $business = PaymentMethodBuilder::create()
            ->withId('business-id')
            ->withHandlerIdentifier(FakeBusinessCustomerAwarePaymentHandler::class)
            ->build()
        ;

        $regular = PaymentMethodBuilder::create()
            ->withId('regular-id')
            ->withHandlerIdentifier(FakePaymentMethodHandler::class)
            ->build()
        ;

        return new PaymentMethodCollection([$business, $regular]);
    }

    private function buildCustomer(string $company): CustomerEntity
    {
        $billingAddress = new CustomerAddressEntity();
        $billingAddress->setId('billing-address-id');
        $billingAddress->setCompany($company);

        return CustomerBuilder::create()
            ->withDefaultBillingAddress($billingAddress)
            ->build()
        ;
    }
}
