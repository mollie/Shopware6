<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Event;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Transaction\TransactionDataStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

final class PaymentCreatedEvent
{
    public function __construct(
        private readonly string $redirectUrl,
        private readonly Payment $payment,
        private readonly TransactionDataStruct $transactionDataStruct,
        private readonly RequestDataBag $requestDataBag,
        private readonly Context $context
    ) {
    }

    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }

    public function getTransactionDataStruct(): TransactionDataStruct
    {
        return $this->transactionDataStruct;
    }

    public function getRequestDataBag(): RequestDataBag
    {
        return $this->requestDataBag;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
