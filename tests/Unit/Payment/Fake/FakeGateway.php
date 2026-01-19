<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Mollie\Capture;
use Mollie\Shopware\Component\Mollie\CreateCapture;
use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\Customer;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Mandate;
use Mollie\Shopware\Component\Mollie\MandateCollection;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\Profile;
use Mollie\Shopware\Component\Mollie\TerminalCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;

final class FakeGateway implements MollieGatewayInterface
{
    public function __construct(private string $checkoutUrl = '',private ?Payment $payment = null)
    {
        if ($payment === null) {
            $payment = new Payment('test');
            $payment->setMethod(PaymentMethod::CREDIT_CARD);
            $payment->setCheckoutUrl($this->checkoutUrl);
            $this->payment = $payment;
        }
    }

    public function createPayment(CreatePayment $molliePayment, string $salesChannelId): Payment
    {
        return $this->payment;
    }

    public function getPaymentByTransactionId(string $transactionId, Context $context): Payment
    {
        return $this->payment;
    }

    public function getCurrentProfile(?string $salesChannelId = null): Profile
    {
        return new Profile('fake_profile', 'fake', 'fake');
    }

    public function createCustomer(CustomerEntity $customer, string $salesChannelId): Customer
    {
        return new Customer('cust_fake_' . uniqid(), 'Fake Customer', 'fake@mollie.test', []);
    }

    public function listMandates(string $mollieCustomerId, string $salesChannelId): MandateCollection
    {
        $collection = new MandateCollection();
        $mandate = new Mandate('tr_test_mandate_id', PaymentMethod::CREDIT_CARD, []);
        $collection->set('tr_test_mandate_id', $mandate);

        return $collection;
    }

    public function revokeMandate(string $mollieCustomerId, string $mandateId, string $salesChannelId): bool
    {
        // TODO: Implement revokeMandate() method.
    }

    public function listTerminals(string $salesChannelId): TerminalCollection
    {
        // TODO: Implement listTerminals() method.
    }

    public function getPayment(string $molliePaymentId, string $orderNumber, string $salesChannelId): Payment
    {
        // TODO: Implement getPayment() method.
    }

    public function createCapture(CreateCapture $createCapture, string $paymentId, string $orderNumber, string $salesChannelId): Capture
    {
        // TODO: Implement createCapture() method.
    }
}
