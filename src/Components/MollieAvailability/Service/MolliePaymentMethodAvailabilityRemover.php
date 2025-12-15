<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\MollieAvailability\Service;

use Kiener\MolliePayments\Exception\MissingCartServiceException;
use Kiener\MolliePayments\Service\MollieApi\OrderItemsExtractor;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\Payment\Provider\ActivePaymentMethodsProviderInterface;
use Kiener\MolliePayments\Service\Payment\Remover\PaymentMethodRemover;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Struct\PaymentMethod\PaymentMethodAttributes;
use Mollie\Api\Resources\Method;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class MolliePaymentMethodAvailabilityRemover extends PaymentMethodRemover
{
    /**
     * @var ActivePaymentMethodsProviderInterface
     */
    private $paymentMethodsProvider;

    public function __construct(ContainerInterface $container, RequestStack $requestStack, OrderService $orderService, SettingsService $settingsService, ActivePaymentMethodsProviderInterface $paymentMethodsProvider, OrderItemsExtractor $orderDataExtractor, LoggerInterface $logger)
    {
        parent::__construct($container, $requestStack, $orderService, $settingsService, $orderDataExtractor, $logger);

        $this->paymentMethodsProvider = $paymentMethodsProvider;
    }

    /**
     * @throws \Exception
     */
    public function removePaymentMethods(PaymentMethodRouteResponse $originalData, SalesChannelContext $context): PaymentMethodRouteResponse
    {
        $settings = $this->settingsService->getSettings($context->getSalesChannelId());

        // if we do not use the limits
        // then just return everything
        if (! $settings->getUseMolliePaymentMethodLimits()) {
            return $originalData;
        }

        if (! $this->isAllowedRoute()) {
            return $originalData;
        }

        $billingAddress = null;
        $countryIsoCode = null;

        if ($this->isCartRoute()) {
            try {
                $cart = $this->getCart($context);
            } catch (MissingCartServiceException $e) {
                $this->logger->error($e->getMessage(), [
                    'exception' => $e,
                ]);

                return $originalData;
            }

            $price = $cart->getPrice()->getTotalPrice();
            $customer = $context->getCustomer();
            if ($customer !== null) {
                $billingAddress = $customer->getDefaultBillingAddress();
            }
        }

        if ($this->isOrderRoute()) {
            try {
                $order = $this->getOrder($context->getContext());
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage(), [
                    'exception' => $e,
                ]);

                return $originalData;
            }

            $price = $order->getAmountTotal();

            $billingAddress = $order->getBillingAddress();
        }

        if (! isset($price) || 0.0 === $price) {
            return $originalData;
        }

        if ($billingAddress !== null) {
            $billingCountry = $billingAddress->getCountry();
            if ($billingCountry !== null) {
                $countryIsoCode = $billingCountry->getIso();
            }
        }

        $availableMolliePayments = $this->paymentMethodsProvider->getActivePaymentMethodsForAmount(
            $price,
            $context->getCurrency()->getIsoCode(),
            (string) $countryIsoCode,
            [
                $context->getSalesChannel()->getId(),
            ]
        );

        /** @var PaymentMethodEntity $paymentMethod */
        foreach ($originalData->getPaymentMethods() as $paymentMethod) {
            $mollieAttributes = new PaymentMethodAttributes($paymentMethod);

            // check if we have even a mollie payment
            // if not, then always keep that payment method
            if (! $mollieAttributes->isMolliePayment()) {
                continue;
            }

            $found = false;

            // now search if we still have it, otherwise just remove it
            /** @var Method $mollieMethod */
            foreach ($availableMolliePayments as $mollieMethod) {
                // if we have found it in the list of available mollie methods
                // then just keep it
                if ($mollieMethod->id === $mollieAttributes->getMollieIdentifier()) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $originalData->getPaymentMethods()->remove($paymentMethod->getId());
            }
        }

        return $originalData;
    }
}
