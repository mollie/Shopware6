<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\Payment;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

#[AsDecorator(decorates: MollieGateway::class)]
final class CachedMollieGateway implements MollieGatewayInterface
{
    /**
     * @var array<string, Payment>
     */
    private array $cache = [];

    public function __construct(
        private MollieGatewayInterface $decorated
    ) {
    }

    public function createPayment(CreatePayment $molliePayment, string $salesChannelId): Payment
    {
        return $this->decorated->createPayment($molliePayment, $salesChannelId);
    }

    public function getPaymentByTransactionId(string $transactionId, Context $context): Payment
    {
        $key = sprintf('%s', $transactionId);
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        $this->cache[$key] = $this->decorated->getPaymentByTransactionId($transactionId, $context);

        return $this->cache[$key];
    }
}
