<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Mollie\Capture;
use Mollie\Shopware\Component\Mollie\CreateCapture;
use Mollie\Shopware\Component\Mollie\CreateOrder;
use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\CreateShipment;
use Mollie\Shopware\Component\Mollie\Customer;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Mandate;
use Mollie\Shopware\Component\Mollie\MandateCollection;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Order;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentCollection;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\Profile;
use Mollie\Shopware\Component\Mollie\Shipment;
use Mollie\Shopware\Component\Mollie\TerminalCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;

final class FakeGateway implements MollieGatewayInterface
{
    /** @var list<CreatePayment> */
    private array $createPayloads = [];

    /** @var list<CreateOrder> */
    private array $createOrderPayloads = [];

    /** @var list<string> */
    private array $cancelledPaymentIds = [];

    /** @var list<string> */
    private array $cancelledOrderIds = [];

    /** @var array<string,PaymentCollection> */
    private array $subscriptionPayments = [];

    private ?Order $order = null;
    private bool $throwOnGetOrder = false;

    /** @var list<string> */
    private array $validApiKeys = [];

    /** @var string[] */
    private array $activePaymentMethods = [];

    public function __construct(private string $checkoutUrl = '',private ?Payment $payment = null)
    {
        if ($payment === null) {
            $payment = new Payment('test');
            $payment->setMethod(PaymentMethod::CREDIT_CARD);
            $payment->setCheckoutUrl($this->checkoutUrl);
            $this->payment = $payment;
        }
    }

    public function registerSubscriptionPayments(string $mollieSubscriptionId, PaymentCollection $payments): void
    {
        $this->subscriptionPayments[$mollieSubscriptionId] = $payments;
    }

    /**
     * @return list<CreatePayment>
     */
    public function getCreatePayloads(): array
    {
        return $this->createPayloads;
    }

    /**
     * @return list<CreateOrder>
     */
    public function getCreateOrderPayloads(): array
    {
        return $this->createOrderPayloads;
    }

    /**
     * @return list<string>
     */
    public function getCancelledPaymentIds(): array
    {
        return $this->cancelledPaymentIds;
    }

    /**
     * @return list<string>
     */
    public function getCancelledOrderIds(): array
    {
        return $this->cancelledOrderIds;
    }

    public function createPayment(CreatePayment $molliePayment, string $salesChannelId): Payment
    {
        $this->createPayloads[] = $molliePayment;

        return $this->payment;
    }

    public function createOrder(CreateOrder $createOrder, string $salesChannelId): Order
    {
        $this->createOrderPayloads[] = $createOrder;

        $order = new Order('ord_fake_' . uniqid(), $this->checkoutUrl);

        return $order->withPayment($this->payment);
    }

    public function getPaymentByTransactionId(string $transactionId, Context $context): Payment
    {
        return $this->payment;
    }

    public function withValidApiKey(string $key): void
    {
        $this->validApiKeys[] = $key;
    }

    public function getCurrentProfile(?string $salesChannelId = null): Profile
    {
        return new Profile('fake_profile', 'fake', 'fake@mollie.test');
    }

    public function getProfileForApiKey(string $apiKey): Profile
    {
        if (! in_array($apiKey, $this->validApiKeys, true)) {
            throw new \RuntimeException('Invalid API key');
        }

        return new Profile('fake_profile', 'fake', 'fake@mollie.test');
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
        return true;
    }

    public function listTerminals(string $salesChannelId): TerminalCollection
    {
        return new TerminalCollection();
    }

    /**
     * @param string[] $activePaymentMethods
     */
    public function withActivePaymentMethods(array $activePaymentMethods): void
    {
        $this->activePaymentMethods = $activePaymentMethods;
    }

    public function getActivePaymentMethods(Money $amount, string $billingCountry, string $salesChannelId): array
    {
        return $this->activePaymentMethods;
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

    public function cancelOrder(string $mollieOrderId, string $orderNumber, string $salesChannelId): Order
    {
        $this->cancelledOrderIds[] = $mollieOrderId;

        return $this->order ?? new Order($mollieOrderId, '');
    }

    public function listSubscriptionPayments(string $mollieCustomerId, string $mollieSubscriptionId, string $orderNumber, string $salesChannelId): PaymentCollection
    {
        return $this->subscriptionPayments[$mollieSubscriptionId] ?? new PaymentCollection();
    }

    public function createCapture(CreateCapture $createCapture, string $paymentId, string $orderNumber, string $salesChannelId): Capture
    {
        // TODO: Implement createCapture() method.
    }

    public function createShipment(CreateShipment $createShipment, string $mollieOrderId, string $orderNumber, string $salesChannelId): Shipment
    {
        return new Shipment('shp_fake_' . uniqid());
    }

    public function withOrder(Order $order): void
    {
        $this->order = $order;
    }

    public function withGetOrderException(): void
    {
        $this->throwOnGetOrder = true;
    }

    public function getOrder(string $mollieOrderId, string $salesChannelId): Order
    {
        if ($this->throwOnGetOrder) {
            throw new \RuntimeException('Mollie API unavailable');
        }

        if ($this->order !== null) {
            return $this->order;
        }

        return new Order($mollieOrderId, '');
    }

    public function cancelOrderLines(string $mollieOrderId, string $mollieLineId, int $quantity, string $orderNumber, string $salesChannelId): void
    {
    }

    public function releaseAuthorization(string $paymentId, string $orderNumber, string $salesChannelId): void
    {
    }
}
