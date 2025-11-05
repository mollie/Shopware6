<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Checkout\Order\OrderEntity;

interface CreatePaymentBuilderInterface
{
    public function build(string $transactionId, OrderEntity $order): CreatePayment;
}
