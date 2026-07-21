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

    /**
     * Builds the payload for a Mollie payment link. Reuses the regular payment payload; the payment
     * method is a list of allowed methods. When exactly one method is allowed, its handler is passed
     * so its payment-specific parameters can be applied.
     *
     * @param string[] $allowedMethods
     */
    public function buildPaymentLink(TransactionDataStruct $transactionData, array $allowedMethods, ?AbstractMolliePaymentHandler $paymentHandler, Context $context): CreatePaymentLink;
}
