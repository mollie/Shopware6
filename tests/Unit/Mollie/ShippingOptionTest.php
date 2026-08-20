<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\ShippingOption;
use Mollie\Shopware\Component\Mollie\ShippingOptionCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShippingOption::class)]
#[CoversClass(ShippingOptionCollection::class)]
final class ShippingOptionTest extends TestCase
{
    public function testSerializesToTheMollieShape(): void
    {
        $shippingOption = new ShippingOption('Next day delivery', 'express', new Money(3.99, 'EUR'));

        $this->assertSame([
            'description' => 'Next day delivery',
            'reference' => 'express',
            'amount' => ['value' => '3.99', 'currency' => 'EUR'],
        ], $shippingOption->toArray());
    }

    public function testCollectionRoundTrip(): void
    {
        $values = [
            ['description' => 'Next day delivery', 'reference' => 'express', 'amount' => ['value' => '3.99', 'currency' => 'EUR']],
            ['description' => 'Free delivery', 'reference' => 'free', 'amount' => ['value' => '0.00', 'currency' => 'EUR']],
        ];

        $collection = ShippingOptionCollection::fromArray($values);

        $this->assertCount(2, $collection);
        $this->assertSame($values, $collection->toArray());
    }

    public function testCollectionSkipsUnusableEntries(): void
    {
        $collection = ShippingOptionCollection::fromArray([
            'not an array',
            ['description' => 'Free delivery', 'reference' => 'free', 'amount' => ['value' => '0.00', 'currency' => 'EUR']],
        ]);

        $this->assertCount(1, $collection);
    }

    public function testFindByReference(): void
    {
        $collection = ShippingOptionCollection::fromArray([
            ['description' => 'Free delivery', 'reference' => 'free', 'amount' => ['value' => '0.00', 'currency' => 'EUR']],
        ]);

        $this->assertNotNull($collection->getByReference('free'));
        $this->assertNull($collection->getByReference('express'));
    }
}
