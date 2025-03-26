<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Handler;

use Mollie\Shopware\Component\Payment\PaymentHandlerLegacyTrait;
use Mollie\Shopware\Component\Payment\PaymentHandlerTrait;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;

if (class_exists(AbstractPaymentHandler::class)) {
    class PaymentHandler extends AbstractPaymentHandler
    {
        use PaymentHandlerTrait;
        public const PAYMENT_SEQUENCE_TYPE_FIRST = 'first';
        public const PAYMENT_SEQUENCE_TYPE_RECURRING = 'recurring';
    }

    return;
}
/** @phpstan-ignore-next-line  */
if (interface_exists(AsynchronousPaymentHandlerInterface::class) && ! class_exists(AbstractPaymentHandler::class)) {
    class PaymentHandler implements AsynchronousPaymentHandlerInterface
    {
        use PaymentHandlerLegacyTrait;
        public const PAYMENT_SEQUENCE_TYPE_FIRST = 'first';
        public const PAYMENT_SEQUENCE_TYPE_RECURRING = 'recurring';
    }
}
