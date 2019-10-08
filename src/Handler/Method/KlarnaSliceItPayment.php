<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;

class KlarnaSliceItPayment extends PaymentHandler
{
    protected $paymentMethod = PaymentMethod::KLARNA_SLICE_IT;
}