<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\MethodRemover\Fake;

use Mollie\Shopware\Component\Payment\MethodRemover\AbstractPaymentRemover;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakePaymentRemover extends AbstractPaymentRemover
{
    public bool $called = false;

    public string $receivedOrderId = '';

    /**
     * @param string[] $removeIds
     */
    public function __construct(private array $removeIds = [])
    {
    }

    public function remove(PaymentMethodCollection $paymentMethods, string $orderId, SalesChannelContext $salesChannelContext): PaymentMethodCollection
    {
        $this->called = true;
        $this->receivedOrderId = $orderId;

        foreach ($this->removeIds as $removeId) {
            $paymentMethods->remove($removeId);
        }

        return $paymentMethods;
    }
}
