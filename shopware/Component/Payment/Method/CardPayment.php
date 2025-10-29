<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Payment\Handler\CompatibilityPaymentHandler;
use Mollie\Shopware\Entity\Customer\Customer;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Order\OrderEntity;

final class CardPayment extends CompatibilityPaymentHandler
{
    protected string $method = 'creditcard';

    public function applyPaymentSpecificParameters(CreatePayment $payment, OrderEntity $orderEntity): CreatePayment
    {
        $shopCustomer = $orderEntity->getOrderCustomer()->getCustomer();
        /** @var ?Customer $mollieCustomer */
        $mollieCustomer = $shopCustomer->getExtension(Mollie::EXTENSION);
        if ($mollieCustomer === null) {
            return $payment;
        }
        $payment->setCardToken($mollieCustomer->getCreditCardToken());

        return $payment;
    }
}
