<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

final class PosPayment extends AbstractMolliePaymentHandler
{
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::POS;
    }

    public function getName(): string
    {
        return 'POS Terminal';
    }

    public function applyPaymentSpecificParameters(CreatePayment $payment,RequestDataBag $dataBag, CustomerEntity $customer): CreatePayment
    {
        $terminalId = $dataBag->get('terminalId');
        if ($terminalId !== null) {
            $payment->setTerminalId($terminalId);
        }

        return $payment;
    }
}
