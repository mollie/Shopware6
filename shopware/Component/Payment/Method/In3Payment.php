<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;

final class In3Payment extends AbstractMolliePaymentHandler
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::IN3;
    }

    public function getName(): string
    {
        return 'iDeal IN3';
    }

    public function getDescription(): string
    {
        return 'iDEAL in3 is a buy now, pay later payment method with guaranteed payouts. With iDEAL in3, your customers in the Netherlands can pay in three interest-free instalments over 60 days via iDEAL.';
    }
}
