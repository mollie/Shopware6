<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\Payment;
use Shopware\Core\Framework\Context;

final class CachedMollieGateway implements MollieGatewayInterface
{
    /**
     * @var array<string, Payment>
     */
    private array $paymentCache = [];
    /**
     * @var array<string, Payment>
     */
    private array $transactionCache = [];

    public function __construct(private MollieGatewayInterface $decorated)
    {
    }

    public function createPayment(CreatePayment $molliePayment, string $salesChannelId): Payment
    {
        return $this->decorated->createPayment($molliePayment, $salesChannelId);
    }

    public function getPayment(string $molliePaymentId, string $salesChannelId): Payment
    {
        $key = sprintf('%s/%s', $salesChannelId, $molliePaymentId);

        if (isset($this->paymentCache[$key])) {
            return $this->paymentCache[$key];
        }
        $this->paymentCache[$key] = $this->decorated->getPayment($molliePaymentId, $salesChannelId);

        return $this->paymentCache[$key];
    }

    public function getPaymentByTransactionId(string $transactionId, Context $context): Payment
    {
        $key = sprintf('%s', $transactionId);
        if (isset($this->transactionCache[$key])) {
            return $this->transactionCache[$key];
        }
        $this->transactionCache[$key] = $this->decorated->getPaymentByTransactionId($transactionId, $context);

        return $this->transactionCache[$key];
    }
}
