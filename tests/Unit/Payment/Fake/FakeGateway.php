<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\Customer;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\MandateCollection;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\Profile;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;

final class FakeGateway implements MollieGatewayInterface
{
    public function __construct(private string $checkoutUrl = '')
    {
    }

    public function createPayment(CreatePayment $molliePayment, string $salesChannelId): Payment
    {
        $payment = new Payment('test', PaymentMethod::CREDIT_CARD);
        $payment->setCheckoutUrl($this->checkoutUrl);

        return $payment;
    }

    public function getPaymentByTransactionId(string $transactionId, Context $context): Payment
    {
        // TODO: Implement getPaymentByTransactionId() method.
    }

    public function getCurrentProfile(?string $salesChannelId = null): Profile
    {
        return new Profile('fake_profile', 'fake', 'fake');
    }

    public function createCustomer(CustomerEntity $customer, string $salesChannelId): Customer
    {
        // TODO: Implement createCustomer() method.
    }

    public function listMandates(string $mollieCustomerId, string $salesChannelId): MandateCollection
    {
        // TODO: Implement listMandates() method.
    }
}
