<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Rule;

use Mollie\Shopware\Component\Subscription\Rule\LineItemSubscriptionRule;
use Mollie\Shopware\Entity\Product\Product;
use Mollie\Shopware\Mollie;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[CoversClass(LineItemSubscriptionRule::class)]
final class LineItemSubscriptionRuleTest extends TestCase
{
    private LineItemSubscriptionRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemSubscriptionRule();
    }

    public function testGetName(): void
    {
        $this->assertSame('mollie_lineitem_subscription_rule', $this->rule->getName());
    }

    public function testGetConstraintsExposesIsSubscription(): void
    {
        $this->assertArrayHasKey('isSubscription', $this->rule->getConstraints());
    }

    #[TestWith([true, false])]
    #[TestWith([false, true])]
    public function testNonSubscriptionLineItem(bool $expected, bool $lookingForSubscription): void
    {
        $lineItem = new LineItem('line-id', 'product');

        $this->rule->assign(['isSubscription' => $lookingForSubscription]);

        $match = $this->rule->match(
            new LineItemScope($lineItem, $this->createMock(SalesChannelContext::class))
        );

        $this->assertSame($expected, $match);
    }

    #[TestWith([true, true])]
    #[TestWith([false, false])]
    public function testSubscriptionLineItem(bool $expected, bool $lookingForSubscription): void
    {
        $extension = new Product();
        $extension->setIsSubscription(true);

        $lineItem = new LineItem('line-id', 'product');
        $lineItem->addExtension(Mollie::EXTENSION, $extension);

        $this->rule->assign(['isSubscription' => $lookingForSubscription]);

        $match = $this->rule->match(
            new LineItemScope($lineItem, $this->createMock(SalesChannelContext::class))
        );

        $this->assertSame($expected, $match);
    }
}
