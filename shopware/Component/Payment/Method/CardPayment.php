<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\PaymentParameterInterface;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\AutomaticCaptureAwareInterface;
use Mollie\Shopware\Component\Payment\Handler\RecurringAwareInterface;
use Mollie\Shopware\Component\Payment\Handler\SubscriptionAwareInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

final class CardPayment extends AbstractMolliePaymentHandler implements SubscriptionAwareInterface, RecurringAwareInterface, AutomaticCaptureAwareInterface
{
    /**
     * The order form field mollie.js writes the card token into.
     */
    public const FIELD_CREDIT_CARD_TOKEN = 'creditCardToken';

    public function applyPaymentSpecificParameters(PaymentParameterInterface $payment, RequestDataBag $dataBag, CustomerEntity $customer): PaymentParameterInterface
    {
        if ($payment->getMandateId() !== null) {
            return $payment;
        }

        // The card form posts an empty hidden token whenever the components are rendered, and the
        // storefront skips the tokenisation when a stored card is selected - so an empty token
        // means "no card entered", not "pay with an empty card".
        $cardToken = (string) $dataBag->get(self::FIELD_CREDIT_CARD_TOKEN);
        if (mb_strlen($cardToken) === 0) {
            return $payment;
        }
        $payment->setCardToken($cardToken);

        $savePaymentDetails = $dataBag->get(self::FIELD_SAVE_PAYMENT_DETAILS, false);
        if ($savePaymentDetails) {
            $payment->storeCredentials();
        }

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
