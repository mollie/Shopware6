<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\MethodRemover;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Payment\PaymentHandlerLocator;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AvailabilityPaymentMethodRemover extends AbstractPaymentRemover
{
    /**
     * @param EntityRepository<EntityCollection<OrderEntity>> $orderRepository
     */
    public function __construct(
        private readonly PaymentHandlerLocator $paymentHandlerLocator,
        #[Autowire(service: MollieGateway::class)]
        private readonly MollieGatewayInterface $mollieGateway,
        private readonly CartService $cartService,
        #[Autowire(service: 'order.repository')]
        private readonly EntityRepository $orderRepository,
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
    ) {
    }

    public function remove(PaymentMethodCollection $paymentMethods, string $orderId, SalesChannelContext $salesChannelContext): PaymentMethodCollection
    {
        $paymentSettings = $this->settingsService->getPaymentSettings($salesChannelContext->getSalesChannelId());
        if ($paymentSettings->useMollieLimits() === false) {
            return $paymentMethods;
        }

        if ($orderId !== '') {
            $amount = $this->resolveOrderAmount($orderId, $salesChannelContext);
            $billingCountry = $this->resolveOrderBillingCountry($orderId, $salesChannelContext);
        } else {
            $amount = $this->resolveCartAmount($salesChannelContext);
            $billingCountry = $this->resolveCartBillingCountry($salesChannelContext);
        }

        if ($amount->getValue() <= 0.0) {
            return $paymentMethods;
        }

        $activeMethodIds = $this->mollieGateway->getActivePaymentMethods($amount, $billingCountry, $salesChannelContext->getSalesChannelId());

        foreach ($paymentMethods as $paymentMethod) {
            $paymentMethodHandler = $this->paymentHandlerLocator->findByIdentifier($paymentMethod->getHandlerIdentifier());

            // keep every payment method that is not a Mollie payment method
            if ($paymentMethodHandler === null) {
                continue;
            }

            if (in_array($paymentMethodHandler->getPaymentMethod()->value, $activeMethodIds, true) === false) {
                $paymentMethods->remove($paymentMethod->getId());
            }
        }

        return $paymentMethods;
    }

    private function resolveCartAmount(SalesChannelContext $salesChannelContext): Money
    {
        $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

        return new Money($cart->getPrice()->getTotalPrice(), $salesChannelContext->getCurrency()->getIsoCode());
    }

    private function resolveCartBillingCountry(SalesChannelContext $salesChannelContext): string
    {
        $customer = $salesChannelContext->getCustomer();
        if ($customer === null) {
            return '';
        }

        $billingAddress = $customer->getActiveBillingAddress() ?? $customer->getDefaultBillingAddress();
        if ($billingAddress === null) {
            return '';
        }

        $country = $billingAddress->getCountry();
        if (! $country instanceof CountryEntity) {
            return '';
        }

        return (string) $country->getIso();
    }

    private function resolveOrderAmount(string $orderId, SalesChannelContext $salesChannelContext): Money
    {
        $order = $this->loadOrder($orderId, $salesChannelContext);
        if ($order === null) {
            return new Money(0.0, $salesChannelContext->getCurrency()->getIsoCode());
        }

        return new Money($order->getAmountTotal(), $salesChannelContext->getCurrency()->getIsoCode());
    }

    private function resolveOrderBillingCountry(string $orderId, SalesChannelContext $salesChannelContext): string
    {
        $order = $this->loadOrder($orderId, $salesChannelContext);
        if ($order === null) {
            return '';
        }

        $billingAddress = $order->getBillingAddress();
        if ($billingAddress === null) {
            return '';
        }

        $country = $billingAddress->getCountry();
        if (! $country instanceof CountryEntity) {
            return '';
        }

        return (string) $country->getIso();
    }

    private function loadOrder(string $orderId, SalesChannelContext $salesChannelContext): ?OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('billingAddress.country');

        $order = $this->orderRepository->search($criteria, $salesChannelContext->getContext())->first();
        if (! $order instanceof OrderEntity) {
            return null;
        }

        return $order;
    }
}
