<?php
declare(strict_types=1);

namespace Mollie\Shopware\Subscriber;

use Mollie\Shopware\Entity\Customer\Customer;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class CustomerSubscriber implements EventSubscriberInterface
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
            $mollieCustomFields = $customerEntity->getTranslated()['customFields'][Mollie::EXTENSION] ?? null;
            if ($mollieCustomFields === null) {
                $mollieCustomFields = $customerEntity->getCustomFields()[Mollie::EXTENSION] ?? null;
                if ($mollieCustomFields === null) {
                    continue;
                }
            }
            $customerIds = $mollieCustomFields['customer_ids'] ?? null;
            if ($customerIds === null) {
                continue;
            }

            $customerEntity->addExtension(Mollie::EXTENSION, new Customer($customerIds));
        }
    }
}
