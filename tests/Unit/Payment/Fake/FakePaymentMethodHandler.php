<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Action\FinalizeInterface;
use Mollie\Shopware\Component\Payment\Action\PayInterface;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class FakePaymentMethodHandler extends AbstractMolliePaymentHandler
{
    public function __construct(
        private readonly PaymentMethod $paymentMethod = PaymentMethod::PAYPAL,
        private readonly string $name = 'Fake payment method',
        ?PayInterface $pay = null,
        ?FinalizeInterface $finalize = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($pay ?? new FakePay(), $finalize ?? new FakeFinalize(), $logger ?? new NullLogger());
    }

    public function getPaymentMethod(): PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
