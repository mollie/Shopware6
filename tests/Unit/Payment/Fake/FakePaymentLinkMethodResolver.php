<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Payment\PaymentLinkMethodResolverInterface;
use Mollie\Shopware\Component\Transaction\TransactionDataStruct;
use Shopware\Core\Framework\Context;

final class FakePaymentLinkMethodResolver implements PaymentLinkMethodResolverInterface
{
    /**
     * @param string[] $methods
     */
    public function __construct(private array $methods = [])
    {
    }

    public function resolve(TransactionDataStruct $transactionData, Context $context): array
    {
        return $this->methods;
    }
}
