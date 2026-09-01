<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressMethod;

use Mollie\Shopware\Component\Payment\ExpressMethod\VisibilityRestriction;
use Mollie\Shopware\Component\Payment\ExpressMethod\VisibilityRestrictionCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(VisibilityRestrictionCollection::class)]
final class VisibilityRestrictionCollectionTest extends TestCase
{
    public function testConfiguredPositionsBecomeRestrictions(): void
    {
        $collection = VisibilityRestrictionCollection::fromArray(['pdp', 'cart']);

        $this->assertSame(['pdp', 'cart'], $collection->toArray());
    }

    public function testAPositionTheMerchantNeverConfiguredIsSkipped(): void
    {
        $collection = VisibilityRestrictionCollection::fromArray(['pdp', null, '', 'cart']);

        $this->assertSame(['pdp', 'cart'], $collection->toArray());
    }

    public function testAPositionThatIsNoLongerSupportedIsSkippedInsteadOfFailing(): void
    {
        $collection = VisibilityRestrictionCollection::fromArray(['pdp', 'removed-in-an-older-version']);

        $this->assertSame(['pdp'], $collection->toArray());
    }

    public function testNoConfigurationYieldsNoRestrictions(): void
    {
        $collection = VisibilityRestrictionCollection::fromArray([]);

        $this->assertSame([], $collection->toArray());
    }

    public function testEveryRestrictionSurvivesTheRoundTripThroughTheConfiguration(): void
    {
        $allValues = array_map(static fn (VisibilityRestriction $case): string => $case->value, VisibilityRestriction::cases());

        $collection = VisibilityRestrictionCollection::fromArray($allValues);

        $this->assertSame($allValues, $collection->toArray());
    }
}
