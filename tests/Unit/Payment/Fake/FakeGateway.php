<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Mollie\Capture;
use Mollie\Shopware\Component\Mollie\CaptureStatus;
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
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

final class FakeGateway implements MollieGatewayInterface
{
    /** @var list<CreatePayment> */
    private array $createPayloads = [];

    /** @var list<CreateOrder> */
    private array $createOrderPayloads = [];

    /** @var list<array{paymentId: string, payment: CreatePayment}> */
    private array $updatePayloads = [];

    /** @var list<string> */
    private array $cancelledPaymentIds = [];

    /** @var list<string> */
    private array $cancelledOrderIds = [];

    /** @var list<array{mollieOrderId: string, mollieLineId: string, quantity: int, orderNumber: string, salesChannelId: string}> */
    private array $cancelledOrderLines = [];

    /** @var list<array{paymentId: string, orderNumber: string, salesChannelId: string}> */
    private array $releasedAuthorizations = [];

    /** @var array<string,PaymentCollection> */
    private array $subscriptionPayments = [];

    /** @var list<CreateCapture> */
    private array $capturePayloads = [];

    /** @var list<CreateShipment> */
    private array $shipmentPayloads = [];

    private bool $throwOnCapture = false;

    private bool $throwOnGetPayment = false;

    private ?\Throwable $cancelFailure = null;

    private ?\Throwable $profileFailure = null;

    private bool $throwOnReleaseAuthorization = false;

    private ?Order $order = null;
    private bool $throwOnGetOrder = false;

    /** @var array<string, int> */
    private array $callCounts = [];

    private ?Payment $repairPayment = null;
    private bool $repairResultConfigured = false;
    private bool $throwOnRepair = false;

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

    public function updatePayment(string $molliePaymentId, CreatePayment $molliePayment, string $orderNumber, string $salesChannelId): Payment
    {
        $this->updatePayloads[] = ['paymentId' => $molliePaymentId, 'payment' => $molliePayment];

        return $this->payment;
    }

    /**
     * @return list<array{paymentId: string, payment: CreatePayment}>
     */
    public function getUpdatePayloads(): array
    {
        return $this->updatePayloads;
    }

    public function createOrder(CreateOrder $createOrder, string $salesChannelId): Order
    {
        $this->createOrderPayloads[] = $createOrder;

        $order = new Order('ord_fake_' . uniqid(), $this->checkoutUrl);

        return $order->withPayment($this->payment);
    }

    public function getPaymentByTransactionId(string $transactionId, Context $context): Payment
    {
        $this->countCall('getPaymentByTransactionId');

        return $this->payment;
    }

    public function withRepairResult(?Payment $payment): void
    {
        $this->repairPayment = $payment;
        $this->repairResultConfigured = true;
    }

    public function withRepairThrowing(): void
    {
        $this->throwOnRepair = true;
    }

    public function getCallCount(string $method): int
    {
        return $this->callCounts[$method] ?? 0;
    }

    public function getRepairCallCount(): int
    {
        return $this->getCallCount('repairLegacyTransaction');
    }

    public function repairLegacyTransaction(OrderTransactionEntity $transaction, OrderEntity $order, Context $context): ?Payment
    {
        $this->countCall('repairLegacyTransaction');

        if ($this->throwOnRepair) {
            throw new \RuntimeException('Mollie API not reachable');
        }

        return $this->repairResultConfigured ? $this->repairPayment : $this->payment;
    }

    public function withValidApiKey(string $key): void
    {
        $this->validApiKeys[] = $key;
    }

    /**
     * The error Mollie answers the profile lookup with, e.g. when the API key is invalid.
     */
    public function withProfileFailure(\Throwable $failure): void
    {
        $this->profileFailure = $failure;
    }

    public function getCurrentProfile(?string $salesChannelId = null): Profile
    {
        $this->countCall('getCurrentProfile');

        if ($this->profileFailure !== null) {
            throw $this->profileFailure;
        }

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
        $this->countCall('listMandates');

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
        $this->countCall('getActivePaymentMethods');

        return $this->activePaymentMethods;
    }

    public function withGetPaymentThrowing(): void
    {
        $this->throwOnGetPayment = true;
    }

    public function getPayment(string $molliePaymentId, string $orderNumber, string $salesChannelId): Payment
    {
        $this->countCall('getPayment');

        if ($this->throwOnGetPayment) {
            throw new \RuntimeException('Mollie API not reachable');
        }

        return $this->payment;
    }

    /**
     * The error Mollie answers a cancel call with, e.g. when the payment can no longer be cancelled.
     */
    public function withCancelFailure(\Throwable $failure): void
    {
        $this->cancelFailure = $failure;
    }

    public function cancelPayment(string $molliePaymentId, string $orderNumber, string $salesChannelId): Payment
    {
        if ($this->cancelFailure !== null) {
            throw $this->cancelFailure;
        }

        $this->cancelledPaymentIds[] = $molliePaymentId;

        return $this->payment;
    }

    public function cancelOrder(string $mollieOrderId, string $orderNumber, string $salesChannelId): Order
    {
        if ($this->cancelFailure !== null) {
            throw $this->cancelFailure;
        }

        $this->cancelledOrderIds[] = $mollieOrderId;

        return $this->order ?? new Order($mollieOrderId, '');
    }

    public function listSubscriptionPayments(string $mollieCustomerId, string $mollieSubscriptionId, string $orderNumber, string $salesChannelId): PaymentCollection
    {
        return $this->subscriptionPayments[$mollieSubscriptionId] ?? new PaymentCollection();
    }

    public function withCaptureThrowing(): void
    {
        $this->throwOnCapture = true;
    }

    public function createCapture(CreateCapture $createCapture, string $paymentId, string $orderNumber, string $salesChannelId): Capture
    {
        if ($this->throwOnCapture) {
            throw new \RuntimeException('Mollie API not reachable');
        }

        $this->capturePayloads[] = $createCapture;

        return new Capture('cap_fake_' . uniqid(), CaptureStatus::PENDING, $createCapture->getAmount());
    }

    /**
     * @return list<CreateCapture>
     */
    public function getCapturePayloads(): array
    {
        return $this->capturePayloads;
    }

    public function createShipment(CreateShipment $createShipment, string $mollieOrderId, string $orderNumber, string $salesChannelId): Shipment
    {
        $this->shipmentPayloads[] = $createShipment;

        return new Shipment('shp_fake_' . uniqid());
    }

    /**
     * @return list<CreateShipment>
     */
    public function getShipmentPayloads(): array
    {
        return $this->shipmentPayloads;
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
        $this->countCall('getOrder');

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
        $this->cancelledOrderLines[] = [
            'mollieOrderId' => $mollieOrderId,
            'mollieLineId' => $mollieLineId,
            'quantity' => $quantity,
            'orderNumber' => $orderNumber,
            'salesChannelId' => $salesChannelId,
        ];
    }

    /**
     * @return list<array{mollieOrderId: string, mollieLineId: string, quantity: int, orderNumber: string, salesChannelId: string}>
     */
    public function getCancelledOrderLines(): array
    {
        return $this->cancelledOrderLines;
    }

    public function withReleaseAuthorizationThrowing(): void
    {
        $this->throwOnReleaseAuthorization = true;
    }

    public function releaseAuthorization(string $paymentId, string $orderNumber, string $salesChannelId): void
    {
        if ($this->throwOnReleaseAuthorization) {
            throw new \RuntimeException('Mollie API not reachable');
        }

        $this->releasedAuthorizations[] = [
            'paymentId' => $paymentId,
            'orderNumber' => $orderNumber,
            'salesChannelId' => $salesChannelId,
        ];
    }

    /**
     * @return list<array{paymentId: string, orderNumber: string, salesChannelId: string}>
     */
    public function getReleasedAuthorizations(): array
    {
        return $this->releasedAuthorizations;
    }

    private function countCall(string $method): void
    {
        $this->callCounts[$method] = ($this->callCounts[$method] ?? 0) + 1;
    }
}
