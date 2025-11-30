<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Entity\Customer\Customer;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

final class CardPayment extends AbstractMolliePaymentHandler
{
    protected string $method = 'creditcard';

    public function applyPaymentSpecificParameters(CreatePayment $payment, OrderEntity $orderEntity): CreatePayment
    {
        $orderCustomer = $orderEntity->getOrderCustomer();

        if (! $orderCustomer instanceof OrderCustomerEntity) {
            return $payment;
        }
        $customerEntity = $orderCustomer->getCustomer();
        if (! $customerEntity instanceof CustomerEntity) {
            return $payment;
        }
        /** @var ?Customer $mollieCustomer */
        $mollieCustomer = $customerEntity->getExtension(Mollie::EXTENSION);
        if ($mollieCustomer === null) {
            return $payment;
        }
        $payment->setCardToken($mollieCustomer->getCreditCardToken());

        return $payment;
    }
}
