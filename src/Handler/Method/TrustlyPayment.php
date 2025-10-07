<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Shopware\Component\Payment\SubscriptionAware;

class TrustlyPayment extends PaymentHandler implements SubscriptionAware
{
    public const PAYMENT_METHOD_NAME = 'trustly';
    public const PAYMENT_METHOD_DESCRIPTION = 'Trustly';

    protected string $paymentMethod = self::PAYMENT_METHOD_NAME;
}
