<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;

class KlarnaPayLaterPayment extends PaymentHandler
{
    protected $paymentMethod = PaymentMethod::KLARNA_PAY_LATER;
}