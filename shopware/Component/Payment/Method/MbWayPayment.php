<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;

final class MbWayPayment extends AbstractMolliePaymentHandler
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::MB_WAY;
    }

    public function getName(): string
    {
        return 'MB Way';
    }

    public function getDescription(): string
    {
        return 'Unlock seamless payments with MB Way, the go-to mobile wallet in Portugal. Supported by 28 leading banks, MB Way powers more than 45% of e-commerce transactions in the country. With the ability to store up to 8 bank cards, customers can pay effortlessly by entering their mobile number and confirming the purchase with a PIN, TouchID, or FaceID.';
    }
}
