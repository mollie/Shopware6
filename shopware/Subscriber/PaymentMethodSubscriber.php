<?php
declare(strict_types=1);

namespace Mollie\Shopware\Subscriber;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Entity\PaymentMethod\PaymentMethod as PaymentMethodExtension;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Payment\PaymentEvents;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class PaymentMethodSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PaymentEvents::PAYMENT_METHOD_LOADED_EVENT => 'onPaymentMethodLoaded',
        ];
    }

    /**
     * @param EntityLoadedEvent<PaymentMethodEntity> $event
     */
    public function onPaymentMethodLoaded(EntityLoadedEvent $event): void
    {
        /** @var PaymentMethodEntity $paymentMethod */
        foreach ($event->getEntities() as $paymentMethod) {
            if ($paymentMethod->hasExtension(Mollie::EXTENSION)) {
                continue;
            }

            $customFields = $paymentMethod->getTranslated()['customFields'];
            $molliePaymentMethod = $customFields['mollie_payment_method_name'] ?? null;
            if ($molliePaymentMethod === null) {
                continue;
            }
            $molliePaymentMethod = PaymentMethod::tryFrom($molliePaymentMethod);
            if ($molliePaymentMethod === null) {
                continue;
            }
            $paymentMethodExtension = new PaymentMethodExtension($molliePaymentMethod);
            $paymentMethod->addExtension(Mollie::EXTENSION, $paymentMethodExtension);
        }
    }
}
