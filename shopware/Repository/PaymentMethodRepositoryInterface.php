<?php
declare(strict_types=1);

namespace Mollie\Shopware\Repository;

use Kiener\MolliePayments\Handler\PaymentHandler;

interface PaymentMethodRepositoryInterface
{
    /**
     * @return PaymentHandler[]
     */
    public function getSubscriptionPaymentMethods(): PaymentMethodCollection;
}
