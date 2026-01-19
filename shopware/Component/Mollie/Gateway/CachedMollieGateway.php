<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use Mollie\Shopware\Component\Mollie\Capture;
use Mollie\Shopware\Component\Mollie\CreateCapture;
use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\Customer;
use Mollie\Shopware\Component\Mollie\MandateCollection;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\Profile;
use Mollie\Shopware\Component\Mollie\TerminalCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

#[AsDecorator(decorates: MollieGateway::class)]
final class CachedMollieGateway implements MollieGatewayInterface
{
    /**
     * @var array<string, Payment>
     */
    private array $transactionPayments = [];

    /**
     * @var array<string, Profile>
     */
    private array $profiles = [];

    /**
     * @var array<string, Payment>
     */
    private array $paymentIdPayments = [];

    public function __construct(
        private MollieGatewayInterface $decorated
    ) {
    }

    public function getCurrentProfile(?string $salesChannelId = null): Profile
    {
        $cacheKey = $salesChannelId ?? 'all';
        if (isset($this->profiles[$cacheKey])) {
            return $this->profiles[$cacheKey];
        }
        $this->profiles[$cacheKey] = $this->decorated->getCurrentProfile($salesChannelId);

        return $this->profiles[$cacheKey];
    }

    public function createPayment(CreatePayment $molliePayment, string $salesChannelId): Payment
    {
        return $this->decorated->createPayment($molliePayment, $salesChannelId);
    }

    public function getPaymentByTransactionId(string $transactionId, Context $context): Payment
    {
        $key = sprintf('%s', $transactionId);
        if (isset($this->transactionPayments[$key])) {
            return $this->transactionPayments[$key];
        }
        $this->transactionPayments[$key] = $this->decorated->getPaymentByTransactionId($transactionId, $context);

        return $this->transactionPayments[$key];
    }

    public function getPayment(string $molliePaymentId, string $orderNumber, string $salesChannelId): Payment
    {
        $key = sprintf('%s', $molliePaymentId);
        if (isset($this->paymentIdPayments[$key])) {
            return $this->paymentIdPayments[$key];
        }
        $this->paymentIdPayments[$key] = $this->decorated->getPayment($molliePaymentId, $orderNumber, $salesChannelId);

        return $this->paymentIdPayments[$key];
    }

    public function createCustomer(CustomerEntity $customer, string $salesChannelId): Customer
    {
        return $this->decorated->createCustomer($customer, $salesChannelId);
    }

    public function listMandates(string $mollieCustomerId, string $salesChannelId): MandateCollection
    {
        // TODO save in array
        return $this->decorated->listMandates($mollieCustomerId, $salesChannelId);
    }

    public function revokeMandate(string $mollieCustomerId, string $mandateId, string $salesChannelId): bool
    {
        return $this->decorated->revokeMandate($mollieCustomerId, $mandateId, $salesChannelId);
    }

    public function listTerminals(string $salesChannelId): TerminalCollection
    {
        return $this->decorated->listTerminals($salesChannelId);
    }

    public function createCapture(CreateCapture $createCapture, string $paymentId, string $orderNumber, string $salesChannelId): Capture
    {
        return $this->decorated->createCapture($createCapture, $paymentId, $orderNumber, $salesChannelId);
    }

    public function clearCache(): void
    {
        $this->paymentIdPayments = [];
        $this->transactionPayments = [];
    }
}
