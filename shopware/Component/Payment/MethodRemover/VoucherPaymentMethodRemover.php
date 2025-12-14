<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\MethodRemover;

use Mollie\Shopware\Component\Payment\Method\VoucherPayment;
use Mollie\Shopware\Entity\Product\Product;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('mollie.method.remover')]
final class VoucherPaymentMethodRemover extends AbstractPaymentRemover
{
    /**
     * @param EntityRepository<EntityCollection<OrderEntity>> $orderRepository
     */
    public function __construct(
        private CartService $cartService,
        #[Autowire(service: 'order.repository')]
        private EntityRepository $orderRepository,
    ) {
    }

    public function remove(PaymentMethodCollection $paymentMethods, string $orderId, SalesChannelContext $salesChannelContext): PaymentMethodCollection
    {
        $filteredPaymentMethods = $paymentMethods->filter(function (PaymentMethodEntity $paymentMethod) {
            return $paymentMethod->getHandlerIdentifier() === VoucherPayment::class;
        });
        $voucherPaymentMethod = $filteredPaymentMethods->first();

        if (! $voucherPaymentMethod instanceof PaymentMethodEntity) {
            return $paymentMethods;
        }

        $voucherPaymentMethodId = $voucherPaymentMethod->getId();
        $hasVoucherItems = false;
        if (mb_strlen($orderId) === 0) {
            $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);
            $hasVoucherItems = $this->hasVoucherLineItemsByCart($cart);
        } else {
            $criteria = new Criteria([$orderId]);
            $criteria->addAssociation('lineItems');
            $orderSearchResult = $this->orderRepository->search($criteria, $salesChannelContext->getContext());
            $orderEntity = $orderSearchResult->first();
            if ($orderEntity instanceof OrderEntity) {
                $hasVoucherItems = $this->hasVoucherLineItemsByOrder($orderEntity);
            }
        }
        if ($hasVoucherItems === false) {
            $paymentMethods->remove($voucherPaymentMethodId);
        }

        return $paymentMethods;
    }

    private function hasVoucherLineItemsByCart(Cart $cart): bool
    {
        /** @var LineItem $lineItem */
        foreach ($cart->getLineItems() as $lineItem) {
            /** @var ?Product $mollieProduct */
            $mollieProduct = $lineItem->getExtension(Mollie::EXTENSION);
            if ($mollieProduct === null) {
                continue;
            }
            $voucherCategories = $mollieProduct->getVoucherCategories();
            if ($voucherCategories->count() === 0) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function hasVoucherLineItemsByOrder(OrderEntity $orderEntity): bool
    {
        $lineItems = $orderEntity->getLineItems();
        if ($lineItems === null) {
            return false;
        }
        foreach ($lineItems as $lineItem) {
            /** @var ?Product $mollieProduct */
            $mollieProduct = $lineItem->getExtension(Mollie::EXTENSION);

            if ($mollieProduct === null) {
                continue;
            }
            $voucherCategories = $mollieProduct->getVoucherCategories();
            if ($voucherCategories->count() === 0) {
                continue;
            }

            return true;
        }

        return false;
    }
}
