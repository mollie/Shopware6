<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Transaction\TransactionDataStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

interface CreatePaymentBuilderInterface
{
    public function build(TransactionDataStruct $transactionData,AbstractMolliePaymentHandler $paymentHandler,RequestDataBag $dataBag,Context $context): CreatePayment;
}
