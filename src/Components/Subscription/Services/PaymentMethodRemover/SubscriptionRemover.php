<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\Services\PaymentMethodRemover;

use Kiener\MolliePayments\Handler\Method\BanContactPayment;
use Kiener\MolliePayments\Handler\Method\BelfiusPayment;
use Kiener\MolliePayments\Handler\Method\CreditCardPayment;
use Kiener\MolliePayments\Handler\Method\DirectDebitPayment;
use Kiener\MolliePayments\Handler\Method\EpsPayment;
use Kiener\MolliePayments\Handler\Method\GiroPayPayment;
use Kiener\MolliePayments\Handler\Method\iDealPayment;
use Kiener\MolliePayments\Handler\Method\PayByBankPayment;
use Kiener\MolliePayments\Handler\Method\PayPalPayment;
use Kiener\MolliePayments\Handler\Method\SofortPayment;
use Kiener\MolliePayments\Handler\Method\TrustlyPayment;
use Kiener\MolliePayments\Service\MollieApi\OrderItemsExtractor;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\Payment\Remover\PaymentMethodRemover;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Struct\PaymentMethod\PaymentMethodAttributes;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class SubscriptionRemover extends PaymentMethodRemover
{
    public const ALLOWED_METHODS = [
        iDealPayment::PAYMENT_METHOD_NAME,
        BanContactPayment::PAYMENT_METHOD_NAME,
        SofortPayment::PAYMENT_METHOD_NAME,
        EpsPayment::PAYMENT_METHOD_NAME,
        GiroPayPayment::PAYMENT_METHOD_NAME,
        BelfiusPayment::PAYMENT_METHOD_NAME,
        CreditCardPayment::PAYMENT_METHOD_NAME,
        PayPalPayment::PAYMENT_METHOD_NAME,
        DirectDebitPayment::PAYMENT_METHOD_NAME,
        TrustlyPayment::PAYMENT_METHOD_NAME,
        PayByBankPayment::PAYMENT_METHOD_NAME,
    ];

    /**
     * @var SettingsService
     */
    private $pluginSettings;

    public function __construct(ContainerInterface $container, RequestStack $requestStack, SettingsService $pluginSettings, OrderService $orderService, SettingsService $settingsService, OrderItemsExtractor $orderDataExtractor, LoggerInterface $logger)
    {
        parent::__construct($container, $requestStack, $orderService, $settingsService, $orderDataExtractor, $logger);

        $this->pluginSettings = $pluginSettings;
    }

    /**
     * @throws \Exception
     */
    public function removePaymentMethods(PaymentMethodRouteResponse $originalData, SalesChannelContext $context): PaymentMethodRouteResponse
    {
        if (! $this->isAllowedRoute()) {
            return $originalData;
        }

        $settings = $this->pluginSettings->getSettings($context->getSalesChannelId());

        if (! $settings->isSubscriptionsEnabled()) {
            return $originalData;
        }

        if ($this->isOrderRoute()) {
            $order = $this->getOrder($context->getContext());
            $isSubscription = $this->isSubscriptionOrder($order, $context->getContext());
        } else {
            $cart = $this->getCart($context);
            $isSubscription = $this->isSubscriptionCart($cart);
        }

        if (! $isSubscription) {
            return $originalData;
        }

        foreach ($originalData->getPaymentMethods() as $key => $paymentMethod) {
            $attributes = new PaymentMethodAttributes($paymentMethod);

            $paymentMethodName = $attributes->getMollieIdentifier();

            if (! in_array($paymentMethodName, self::ALLOWED_METHODS)) {
                $originalData->getPaymentMethods()->remove($key);
            }
        }

        return $originalData;
    }
}
