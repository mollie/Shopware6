<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\MethodRemover;

use Mollie\Shopware\Component\Payment\Handler\SubscriptionAwareInterface;
use Mollie\Shopware\Component\Payment\PaymentHandlerLocator;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Entity\Product\Product;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SubscriptionRemover extends AbstractPaymentRemover
{
    /**
     * @param EntityRepository<EntityCollection<OrderEntity>> $orderRepository
     */
    public function __construct(
        private CartService $cartService,
        #[Autowire(service: 'order.repository')]
        private EntityRepository $orderRepository,
        private PaymentHandlerLocator $paymentHandlerLocator,
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
    ) {
    }

    public function remove(PaymentMethodCollection $paymentMethods, string $orderId, SalesChannelContext $salesChannelContext): PaymentMethodCollection
    {
        $subscriptionSettings = $this->settingsService->getSubscriptionSettings($salesChannelContext->getSalesChannelId());
        if (! $subscriptionSettings->isEnabled()) {
            return $paymentMethods;
        }
        $hasSubscriptionProducts = false;
        if (mb_strlen($orderId) === 0) {
            $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);
            $hasSubscriptionProducts = $this->hasSubscriptionProductInCart($cart);
        } else {
            $criteria = new Criteria([$orderId]);
            $criteria->addAssociation('lineItems');
            $orderSearchResult = $this->orderRepository->search($criteria, $salesChannelContext->getContext());
            $orderEntity = $orderSearchResult->first();
            if ($orderEntity instanceof OrderEntity) {
                $hasSubscriptionProducts = $this->hasSubscriptionProductInOrder($orderEntity);
            }
        }
        if (! $hasSubscriptionProducts) {
            return $paymentMethods;
        }

        foreach ($paymentMethods as $paymentMethod) {
            $paymentMethodHandler = $this->paymentHandlerLocator->findByIdentifier($paymentMethod->getHandlerIdentifier());
            if (! $paymentMethodHandler instanceof SubscriptionAwareInterface) {
                $paymentMethods->remove($paymentMethod->getId());
            }
        }

        return $paymentMethods;
    }

    private function hasSubscriptionProductInCart(Cart $cart): bool
    {
        $lineItems = $cart->getLineItems();
        foreach ($lineItems as $lineItem) {
            /** @var ?Product $extension */
            $extension = $lineItem->getExtension(Mollie::EXTENSION);
            if ($extension instanceof Product && $extension->isSubscription() === true) {
                return true;
            }
        }

        return false;
    }

    private function hasSubscriptionProductInOrder(OrderEntity $order): bool
    {
        $lineItems = $order->getLineItems();
        if ($lineItems === null) {
            return false;
        }
        foreach ($lineItems as $lineItem) {
            /** @var ?Product $extension */
            $extension = $lineItem->getExtension(Mollie::EXTENSION);
            if ($extension instanceof Product && $extension->isSubscription() === true) {
                return true;
            }
        }

        return false;
    }
}
