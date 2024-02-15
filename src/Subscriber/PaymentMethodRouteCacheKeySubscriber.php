<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\Event\PaymentMethodRouteCacheKeyEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PaymentMethodRouteCacheKeySubscriber implements EventSubscriberInterface
{
    /**
     * @var CartService
     */
    private $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentMethodRouteCacheKeyEvent::class => 'onInvalidateCache'
        ];
    }

    /**
     * shopware caches the payment methods only based on criteria. we add the cart price, currency and billing address id
     * @param PaymentMethodRouteCacheKeyEvent $event
     * @return void
     */
    public function onInvalidateCache(PaymentMethodRouteCacheKeyEvent $event)
    {
        $context = $event->getContext();

        $customer = $context->getCustomer();
        if ($customer === null) {
            return;
        }
        $billingAddress = $customer->getActiveBillingAddress();
        $billingAddressId = '';
        if ($billingAddress !== null) {
            $billingAddressId = $billingAddress->getId();
        }

        $cart = $this->cartService->getCart($context->getToken(), $context);

        $cacheParts = $event->getParts();
        
        $cacheParts[] = md5(implode([
            $cart->getPrice()->getTotalPrice(),
            $context->getCurrency()->getIsoCode(),
            $billingAddressId
        ]));

        $event->setParts($cacheParts);
    }
}
