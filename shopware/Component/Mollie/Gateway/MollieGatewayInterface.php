<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\Payment;
use Shopware\Core\Framework\Context;

interface MollieGatewayInterface
{
    public function createPayment(CreatePayment $molliePayment, string $salesChannelId): Payment;

    public function getPayment(string $molliePaymentId, string $salesChannelId): Payment;

    public function getPaymentByTransactionId(string $transactionId, Context $context): Payment;
}
