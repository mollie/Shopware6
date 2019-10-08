<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;

class Przelewy24Payment extends PaymentHandler
{
    protected $paymentMethod = PaymentMethod::PRZELEWY24;
}