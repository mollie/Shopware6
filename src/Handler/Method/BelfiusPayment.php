<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;

class BelfiusPayment extends PaymentHandler
{
    protected $paymentMethod = PaymentMethod::BELFIUS;
}