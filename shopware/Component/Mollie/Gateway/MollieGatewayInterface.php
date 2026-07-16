<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use Mollie\Shopware\Component\Mollie\Capture;
use Mollie\Shopware\Component\Mollie\CreateCapture;
use Mollie\Shopware\Component\Mollie\CreateOrder;
use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\CreateShipment;
use Mollie\Shopware\Component\Mollie\Customer;
use Mollie\Shopware\Component\Mollie\MandateCollection;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Order;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentCollection;
use Mollie\Shopware\Component\Mollie\Profile;
use Mollie\Shopware\Component\Mollie\Shipment;
use Mollie\Shopware\Component\Mollie\TerminalCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

interface MollieGatewayInterface
{
    public function createPayment(CreatePayment $molliePayment, string $salesChannelId): Payment;

    public function repairLegacyTransaction(OrderTransactionEntity $transaction, OrderEntity $order, Context $context): ?Payment;

    public function createOrder(CreateOrder $createOrder, string $salesChannelId): Order;

    public function getPaymentByTransactionId(string $transactionId, Context $context): Payment;

    public function getPayment(string $molliePaymentId, string $orderNumber, string $salesChannelId): Payment;

    public function cancelPayment(string $molliePaymentId, string $orderNumber, string $salesChannelId): Payment;

    public function cancelOrder(string $mollieOrderId, string $orderNumber, string $salesChannelId): Order;

    public function listSubscriptionPayments(string $mollieCustomerId, string $mollieSubscriptionId, string $orderNumber, string $salesChannelId): PaymentCollection;

    public function getCurrentProfile(?string $salesChannelId = null): Profile;

    public function getProfileForApiKey(string $apiKey): Profile;

    public function createCustomer(CustomerEntity $customer,string $salesChannelId): Customer;

    public function listMandates(string $mollieCustomerId, string $salesChannelId): MandateCollection;

    public function listTerminals(string $salesChannelId): TerminalCollection;

    /**
     * Returns the ids of the Mollie payment methods that are active for the given amount and billing country.
     *
     * @return string[]
     */
    public function getActivePaymentMethods(Money $amount, string $billingCountry, string $salesChannelId): array;

    public function revokeMandate(string $mollieCustomerId, string $mandateId, string $salesChannelId): bool;

    public function getOrder(string $mollieOrderId, string $salesChannelId): Order;

    public function createCapture(CreateCapture $createCapture, string $paymentId, string $orderNumber, string $salesChannelId): Capture;

    public function createShipment(CreateShipment $createShipment, string $mollieOrderId, string $orderNumber, string $salesChannelId): Shipment;

    public function cancelOrderLines(string $mollieOrderId, string $mollieLineId, int $quantity, string $orderNumber, string $salesChannelId): void;

    public function releaseAuthorization(string $paymentId, string $orderNumber, string $salesChannelId): void;
}
