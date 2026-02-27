<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Mollie\Shopware\Component\Mollie\Payment;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;

interface CopyOrderServiceInterface
{
    public function copy(SubscriptionDataStruct $subscriptionData, Payment $payment, Context $context): OrderTransactionEntity;
}
