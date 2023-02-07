<?php

namespace MolliePayments\Tests\Components\Subscription\DAL\Subscription;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionCollection;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

class SubscriptionCollectionTest extends TestCase
{


    /**
     * This test verifies that our flat list does not
     * throw errors when being used with an empty subscription list.
     *
     * @return void
     */
    public function testFlatListIfEmpty()
    {
        $collection = new SubscriptionCollection();

        $this->assertCount(0, $collection->getFlatList());
    }


    /**
     * This test verifies that our key based collection can be
     * converted into a simple array list. there should not be a key with
     * the entity IDs anymore, just a simple list
     *
     * @return void
     */
    public function testFlatList()
    {
        $sub1 = new SubscriptionEntity();
        $sub1->setId(Uuid::randomHex());

        $sub2 = new SubscriptionEntity();
        $sub2->setId(Uuid::randomHex());

        $collection = new SubscriptionCollection([$sub1, $sub2]);

        $flatList = $collection->getFlatList();

        # we should have a list of 2
        $this->assertCount(2, $flatList);
        # our keys must be 0,1, ...and NOT the IDs of our entities
        $this->assertEquals(0, array_keys($flatList)[0]);
        $this->assertEquals(1, array_keys($flatList)[1]);
    }

}
