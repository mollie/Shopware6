<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;

interface PaymentMethodRepositoryInterface
{
    public function getIdByPaymentHandler(string $handlerIdentifier, string $salesChannelId, Context $context): ?string;

    public function getIdByPaymentMethod(PaymentMethod $paymentMethod, string $salesChannelId, Context $context): ?string;

    /**
     * @return PaymentMethodCollection<PaymentMethodEntity>
     */
    public function findAllMollieMethods(Context $context): PaymentMethodCollection;

    /**
     * @return PaymentMethodCollection<PaymentMethodEntity>
     */
    public function findActiveMollieMethods(string $salesChannelId, Context $context): PaymentMethodCollection;
}
