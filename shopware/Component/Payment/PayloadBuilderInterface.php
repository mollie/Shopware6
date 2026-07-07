<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

use Mollie\Shopware\Component\Mollie\CreateOrder;
use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\CreatePaymentLink;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Transaction\TransactionDataStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

interface PayloadBuilderInterface
{
    public function buildPayment(TransactionDataStruct $transactionData, AbstractMolliePaymentHandler $paymentHandler, RequestDataBag $dataBag, Context $context): CreatePayment;

    public function buildOrder(TransactionDataStruct $transactionData, AbstractMolliePaymentHandler $paymentHandler, RequestDataBag $dataBag, Context $context): CreateOrder;

    public function buildPaymentLink(TransactionDataStruct $transactionData, Context $context): CreatePaymentLink;
}
