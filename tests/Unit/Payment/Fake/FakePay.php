<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Payment\Action\PayInterface;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Transaction\MollieTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class FakePay implements PayInterface
{
    private int $callCount = 0;

    private ?MollieTransactionStruct $lastTransaction = null;

    public function __construct(private readonly ?\Throwable $failure = null)
    {
    }

    public function execute(
        AbstractMolliePaymentHandler $paymentHandler,
        MollieTransactionStruct $transaction,
        RequestDataBag $dataBag,
        Context $context
    ): RedirectResponse {
        ++$this->callCount;
        $this->lastTransaction = $transaction;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return new RedirectResponse('https://mollie.com/checkout');
    }

    public function getCallCount(): int
    {
        return $this->callCount;
    }

    public function getLastTransaction(): ?MollieTransactionStruct
    {
        return $this->lastTransaction;
    }
}
