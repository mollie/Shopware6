<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Handler;

use Mollie\Shopware\Component\Payment\Action\Finalize;
use Mollie\Shopware\Component\Payment\Action\Pay;
use Mollie\Shopware\Component\Transaction\TransactionConverterInterface;

trait HandlerTrait
{
    protected string $method;

    public function __construct(private Pay $pay,
                                private Finalize $finalize,
                                private TransactionConverterInterface $transactionConverter)
    {
    }

    public function getPaymentMethodName(): string
    {
        return $this->method;
    }
}
