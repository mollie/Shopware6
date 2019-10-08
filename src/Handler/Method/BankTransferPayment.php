<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;

class BankTransferPayment extends PaymentHandler
{
    protected $paymentMethod = PaymentMethod::BANKTRANSFER;
}