<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\FlowBuilder\Storer;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\FlowStorer;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\FlowEventAware;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Makes the subscription available to flow mail templates as {{ subscription }}.
 * Without a storer the dispatched event's entity is not restored at flow
 * execution time, so the template would render against null. The subscription
 * is reloaded with the associations the templates use (currency, order line
 * items).
 */
#[AutoconfigureTag('flow.storer')]
final class SubscriptionStorer extends FlowStorer
{
    private const DATA_KEY = 'subscription';

    /**
     * @param EntityRepository<SubscriptionCollection<SubscriptionEntity>> $subscriptionRepository
     */
    public function __construct(
        #[Autowire(service: 'mollie_subscription.repository')]
        private readonly EntityRepository $subscriptionRepository
    ) {
    }

    public function store(FlowEventAware $event, array $stored): array
    {
        if (! $event instanceof SubscriptionAware || isset($stored[SubscriptionAware::STORAGE_KEY_SUBSCRIPTION])) {
            return $stored;
        }
        $stored[SubscriptionAware::STORAGE_KEY_SUBSCRIPTION] = $event->getSubscriptionId();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (! $storable->hasStore(SubscriptionAware::STORAGE_KEY_SUBSCRIPTION)) {
            return;
        }
        $storable->lazy(self::DATA_KEY, $this->lazyLoad(...));
    }

    private function lazyLoad(StorableFlow $storableFlow): ?SubscriptionEntity
    {
        $id = $storableFlow->getStore(SubscriptionAware::STORAGE_KEY_SUBSCRIPTION);
        if (! is_string($id) || $id === '') {
            return null;
        }

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('currency');
        $criteria->addAssociation('order.lineItems');

        return $this->subscriptionRepository->search($criteria, $storableFlow->getContext())->getEntities()->first();
    }
}
