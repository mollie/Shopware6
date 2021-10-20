<?php

namespace Kiener\MolliePayments\Compatibility\Storefront\Route\PaymentMethodRoute\Cache;


use Kiener\MolliePayments\Service\Cart\Voucher\VoucherCartCollector;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\Event\PaymentMethodRouteCacheKeyEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


class CachedPaymentMethodRoute64 implements EventSubscriberInterface
{

    /**
     * @var CartService
     */
    private $cartService;


    /**
     * @param CartService $cartService
     */
    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }


    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PaymentMethodRouteCacheKeyEvent::class => 'onGenerateCacheKey',
        ];
    }

    /**
     * This function will make sure that we have a working cache key for all dynamic payment method
     * situations that could occur.
     * So we need to determine if we have a voucher product in it, or not...otherwise the dynamic display
     * of these payment methods (in other route handlers) would not work.
     * @param PaymentMethodRouteCacheKeyEvent $event
     */
    public function onGenerateCacheKey(PaymentMethodRouteCacheKeyEvent $event): void
    {
        $cart = $this->cartService->getCart($event->getContext()->getToken(), $event->getContext());

        $parts = $event->getParts();

        $parts = $this->addVoucherKey($cart, $parts);

        $event->setParts($parts);
    }

    /**
     * @param Cart $cart
     * @param array<mixed> $parts
     *
     * @return array<mixed>
     */
    private function addVoucherKey(Cart $cart, array $parts): array
    {
        $voucherPermitted = (bool)$cart->getData()->get(VoucherCartCollector::VOUCHER_PERMITTED);

        if ($voucherPermitted) {
            $parts[] = 'with-voucher';
        } else {
            $parts[] = 'without-voucher';
        }

        return $parts;
    }

}
