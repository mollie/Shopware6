<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;

final class SatisPayPayment extends AbstractMolliePaymentHandler
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::SATISPAY;
    }

    public function getName(): string
    {
        return 'Satispay';
    }

    public function getDescription(): string
    {
        return 'Accepting Satispay empowers users to make swift and hassle-free transactions directly from their smartphones. By linking bank accounts or payment cards to the Satispay app, individuals and businesses can enjoy a frictionless payment experience. Whether settling bills at local merchants or facilitating money transfers, it is at the forefront of simplifying financial transactions, making everyday payments a breeze.';
    }
}
