<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\ManualCaptureModeAwareInterface;

final class BilliePayment extends AbstractMolliePaymentHandler implements ManualCaptureModeAwareInterface
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::BILLIE;
    }

    public function getName(): string
    {
        return 'Billie';
    }

    public function getDescription(): string
    {
        return "Integrate Europe's leading B2B buy now‚ pay later payment method, Billie. With Billie payments‚ business buyers get flexible payment terms while merchants receive a guaranteed payout.";
    }
}
