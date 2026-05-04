<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Fake;

use Mollie\Shopware\Component\Mollie\Capture;
use Mollie\Shopware\Component\Mollie\CreateCapture;
use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\Customer;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\MandateCollection;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\Profile;
use Mollie\Shopware\Component\Mollie\TerminalCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;

final class FakeTrackingMollieGateway implements MollieGatewayInterface
{
    /** @var list<CreatePayment> */
    private array $createPayloads = [];

    /** @var list<string> */
    private array $cancelledPaymentIds = [];

    public function __construct(private readonly Payment $payment)
    {
    }

    /**
     * @return list<CreatePayment>
     */
    public function getCreatePayloads(): array
    {
        return $this->createPayloads;
    }

    /**
     * @return list<string>
     */
    public function getCancelledPaymentIds(): array
    {
        return $this->cancelledPaymentIds;
    }

    public function createPayment(CreatePayment $molliePayment, string $salesChannelId): Payment
    {
        $this->createPayloads[] = $molliePayment;

        return $this->payment;
    }

    public function getPaymentByTransactionId(string $transactionId, Context $context): Payment
    {
        return $this->payment;
    }

    public function getPayment(string $molliePaymentId, string $orderNumber, string $salesChannelId): Payment
    {
        return $this->payment;
    }

    public function cancelPayment(string $molliePaymentId, string $orderNumber, string $salesChannelId): Payment
    {
        $this->cancelledPaymentIds[] = $molliePaymentId;

        return $this->payment;
    }

    public function getCurrentProfile(?string $salesChannelId = null): Profile
    {
        return new Profile('fake_profile', 'fake', 'fake');
    }

    public function createCustomer(CustomerEntity $customer, string $salesChannelId): Customer
    {
        return new Customer('cust_fake', 'Fake', 'fake@mollie.test', []);
    }

    public function listMandates(string $mollieCustomerId, string $salesChannelId): MandateCollection
    {
        return new MandateCollection();
    }

    public function listTerminals(string $salesChannelId): TerminalCollection
    {
        return new TerminalCollection();
    }

    public function revokeMandate(string $mollieCustomerId, string $mandateId, string $salesChannelId): bool
    {
        return true;
    }

    public function createCapture(CreateCapture $createCapture, string $paymentId, string $orderNumber, string $salesChannelId): Capture
    {
        throw new \LogicException('TrackingMollieGateway::createCapture not implemented');
    }
}
