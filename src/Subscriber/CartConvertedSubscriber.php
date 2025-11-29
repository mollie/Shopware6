<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class CartConvertedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CartConvertedEvent::class => 'savePayPalExpressData',
        ];
    }

    public function savePayPalExpressData(CartConvertedEvent $event): void
    {
        $cart = $event->getCart();
        $cartExtension = $cart->getExtension(CustomFieldsInterface::MOLLIE_KEY);
        if (! $cartExtension instanceof ArrayStruct) {
            return;
        }
        $paypalExpressAuthenticateId = $cartExtension[CustomFieldsInterface::PAYPAL_EXPRESS_AUTHENTICATE_ID] ?? null;
        if ($paypalExpressAuthenticateId === null) {
            return;
        }

        $convertedCart = $event->getConvertedCart();
        $convertedCart['customFields'][CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::PAYPAL_EXPRESS_AUTHENTICATE_ID] = $paypalExpressAuthenticateId;
        $event->setConvertedCart($convertedCart);
    }
}
