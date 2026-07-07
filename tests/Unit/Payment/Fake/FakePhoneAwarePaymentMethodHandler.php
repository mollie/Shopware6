<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\PaymentParameterInterface;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

/**
 * Mimics handlers like Bancomat Pay / Bizum that overwrite the billing phone number
 * from a storefront input inside applyPaymentSpecificParameters().
 */
final class FakePhoneAwarePaymentMethodHandler extends AbstractMolliePaymentHandler
{
    public function __construct()
    {
    }

    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::BANCOMAT_PAY;
    }

    public function getName(): string
    {
        return 'Fake phone aware payment method';
    }

    public function applyPaymentSpecificParameters(PaymentParameterInterface $payment, RequestDataBag $dataBag, CustomerEntity $customer): PaymentParameterInterface
    {
        $billingAddress = $payment->getBillingAddress();
        $billingAddress->setPhone((string) $dataBag->get('molliePayPhone', $billingAddress->getPhone()));

        return $payment;
    }
}
