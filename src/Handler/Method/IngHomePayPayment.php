<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;

class IngHomePayPayment extends PaymentHandler
{
    protected $paymentMethod = PaymentMethod::INGHOMEPAY;
}