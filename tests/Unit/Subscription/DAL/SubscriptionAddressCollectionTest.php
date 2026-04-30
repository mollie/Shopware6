<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\DAL;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubscriptionAddressCollection::class)]
final class SubscriptionAddressCollectionTest extends TestCase
{
    public function testCollectionAcceptsSubscriptionAddressEntities(): void
    {
        $address = new SubscriptionAddressEntity();
        $address->setUniqueIdentifier('address-1');

        $collection = new SubscriptionAddressCollection([$address]);

        $this->assertCount(1, $collection);
        $this->assertSame($address, $collection->first());
    }
}
