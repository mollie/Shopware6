<?php
declare(strict_types=1);

namespace Mollie\Shopware\Subscriber;

use Mollie\Shopware\Entity\Customer\Customer;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[AutoconfigureTag('kernel.event_subscriber')]
final class CustomerLoadedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CustomerEvents::CUSTOMER_LOADED_EVENT => 'onCustomerLoaded',
        ];
    }

    /**
     * @param EntityLoadedEvent<CustomerEntity> $event
     */
    public function onCustomerLoaded(EntityLoadedEvent $event): void
    {
        /** @var CustomerEntity $customerEntity */
        foreach ($event->getEntities() as $customerEntity) {
            if ($customerEntity->hasExtension(Mollie::EXTENSION)) {
                continue;
            }
            $mollieCustomFields = $customerEntity->getCustomFields()[Mollie::EXTENSION] ?? null;
            if ($mollieCustomFields === null) {
                continue;
            }

            $customerEntity->addExtension(Mollie::EXTENSION, new Customer(...$mollieCustomFields));
        }
    }
}
