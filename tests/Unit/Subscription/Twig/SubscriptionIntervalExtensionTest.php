<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Twig;

use Mollie\Shopware\Component\Mollie\Interval;
use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Subscription\Twig\SubscriptionIntervalExtension;
use Mollie\Shopware\Entity\Product\Product;
use Mollie\Shopware\Unit\Fake\FakeTranslator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubscriptionIntervalExtension::class)]
final class SubscriptionIntervalExtensionTest extends TestCase
{
    public function testReturnsEmptyStringForNullProduct(): void
    {
        $extension = new SubscriptionIntervalExtension(new FakeTranslator());

        $this->assertSame('', $extension->translateInterval(null));
    }

    public function testReturnsEmptyStringForNonSubscriptionProduct(): void
    {
        $product = new Product();
        $product->setIsSubscription(false);

        $extension = new SubscriptionIntervalExtension(new FakeTranslator());

        $this->assertSame('', $extension->translateInterval($product));
    }

    public function testRendersSingularSnippetForIntervalOfOneWithoutRepetition(): void
    {
        $product = $this->buildProduct(1, IntervalUnit::WEEKS, 0);

        $extension = new SubscriptionIntervalExtension(new FakeTranslator());

        $this->assertSame(
            'molliePayments.subscriptions.options.everyWeek(%25value%25=1)',
            $extension->translateInterval($product)
        );
    }

    public function testRendersPluralSnippetForIntervalAboveOne(): void
    {
        $product = $this->buildProduct(3, IntervalUnit::DAYS, 0);

        $extension = new SubscriptionIntervalExtension(new FakeTranslator());

        $this->assertSame(
            'molliePayments.subscriptions.options.everyDays(%25value%25=3)',
            $extension->translateInterval($product)
        );
    }

    public function testAppendsRepetitionCountWhenRepetitionIsAtLeastOne(): void
    {
        $product = $this->buildProduct(2, IntervalUnit::MONTHS, 5);

        $extension = new SubscriptionIntervalExtension(new FakeTranslator());

        $this->assertSame(
            'molliePayments.subscriptions.options.everyMonths(%25value%25=2), molliePayments.subscriptions.options.repetitionCount(%25value%25=5)',
            $extension->translateInterval($product)
        );
    }

    public function testExposesMollieSubscriptionIntervalFilter(): void
    {
        $extension = new SubscriptionIntervalExtension(new FakeTranslator());

        $names = array_map(
            function (\Twig\TwigFilter $filter): string {
                return $filter->getName();
            },
            $extension->getFilters()
        );

        $this->assertSame(['mollie_subscription_interval'], $names);
    }

    private function buildProduct(int $intervalValue, IntervalUnit $unit, int $repetition): Product
    {
        $product = new Product();
        $product->setIsSubscription(true);
        $product->setInterval(new Interval($intervalValue, $unit));
        $product->setRepetition($repetition);

        return $product;
    }
}
