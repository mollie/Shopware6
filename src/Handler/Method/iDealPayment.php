<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;

class iDealPayment extends PaymentHandler
{
    protected $paymentMethod = PaymentMethod::IDEAL;
}