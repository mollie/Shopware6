<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Handler;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Payment\Action\Finalize;
use Mollie\Shopware\Component\Payment\Action\Pay;
use Mollie\Shopware\Component\Transaction\TransactionConverterInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;

trait HandlerTrait
{
    protected string $method;

    public function __construct(private Pay $pay,
                                private Finalize $finalize,
                                private TransactionConverterInterface $transactionConverter,
                                private LoggerInterface $logger,
    ) {
    }

    public function applyPaymentSpecificParameters(CreatePayment $payment, OrderEntity $orderEntity): CreatePayment
    {
        return $payment;
    }

    public function getPaymentMethodName(): string
    {
        return $this->method;
    }
}
