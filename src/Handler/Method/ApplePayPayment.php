<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;

class ApplePayPayment extends PaymentHandler
{
    protected $paymentMethod = PaymentMethod::APPLEPAY;
}