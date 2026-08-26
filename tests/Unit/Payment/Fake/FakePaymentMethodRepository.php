<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\PaymentMethodRepositoryInterface;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\Context;

final class FakePaymentMethodRepository implements PaymentMethodRepositoryInterface
{
    private ?\Throwable $findAllFailure = null;

    public function __construct(
        private ?string $fakeId = null,
        private PaymentMethodCollection $methods = new PaymentMethodCollection(),
    ) {
    }

    /**
     * The error the DAL raises when the payment method table cannot be read.
     */
    public function withFindAllFailure(\Throwable $failure): void
    {
        $this->findAllFailure = $failure;
    }

    public function getIdByPaymentHandler(string $handlerIdentifier, string $salesChannelId, Context $context): ?string
    {
        return $this->fakeId;
    }

    public function getIdByPaymentMethod(PaymentMethod $paymentMethod, string $salesChannelId, Context $context): ?string
    {
        return $this->fakeId;
    }

    public function findAllMollieMethods(Context $context): PaymentMethodCollection
    {
        if ($this->findAllFailure !== null) {
            throw $this->findAllFailure;
        }

        return $this->methods;
    }
}
