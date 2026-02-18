<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\RecurringAwareInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

final class FakeRecurringAwarePaymentHandler extends AbstractMolliePaymentHandler implements RecurringAwareInterface
{
    public function __construct(
    ) {
    }

    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::CREDIT_CARD;
    }

    public function applyPaymentSpecificParameters(CreatePayment $payment, RequestDataBag $dataBag, CustomerEntity $customer): CreatePayment
    {
        return $payment;
    }

    public function getName(): string
    {
        return 'fake_recurring_handler';
    }
}
