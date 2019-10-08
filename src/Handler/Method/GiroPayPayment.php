<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;

class GiroPayPayment extends PaymentHandler
{
    protected $paymentMethod = PaymentMethod::GIROPAY;
}