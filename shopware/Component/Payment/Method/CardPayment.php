<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\SubscriptionAwareInterface;
use Mollie\Shopware\Entity\Customer\Customer;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

final class CardPayment extends AbstractMolliePaymentHandler implements SubscriptionAwareInterface
{
    public function applyPaymentSpecificParameters(CreatePayment $payment, RequestDataBag $dataBag, OrderEntity $orderEntity, CustomerEntity $customer): CreatePayment
    {
        /** @var ?Customer $mollieCustomer */
        $mollieCustomer = $customer->getExtension(Mollie::EXTENSION);
        if ($mollieCustomer === null) {
            return $payment;
        }
        $payment->setCardToken($mollieCustomer->getCreditCardToken());

        return $payment;
    }

    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::CREDIT_CARD;
    }

    public function getName(): string
    {
        return 'Card';
    }
}
