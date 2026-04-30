<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\DAL;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionHistory\SubscriptionHistoryEntity;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubscriptionHistoryEntity::class)]
final class SubscriptionHistoryEntityTest extends TestCase
{
    public function testGettersReflectSetterValuesForAllProperties(): void
    {
        $entity = new SubscriptionHistoryEntity();
        $subscription = new SubscriptionEntity();

        $entity->setSubscriptionId('subscription-id');
        $entity->setStatusFrom('active');
        $entity->setStatusTo('paused');
        $entity->setComment('paused after 2026-04-30');
        $entity->setMollieId('sub_test123');
        $entity->setSubscription($subscription);

        $this->assertSame('subscription-id', $entity->getSubscriptionId());
        $this->assertSame('active', $entity->getStatusFrom());
        $this->assertSame('paused', $entity->getStatusTo());
        $this->assertSame('paused after 2026-04-30', $entity->getComment());
        $this->assertSame('sub_test123', $entity->getMollieId());
        $this->assertSame($subscription, $entity->getSubscription());
    }
}
