<?php

namespace MolliePayments\Tests\Components\Subscription\Rule;

use Kiener\MolliePayments\Components\Subscription\Rule\LineItemSubscriptionRule;
use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class LineItemSubscriptionRuleTest extends TestCase
{
    /**
     * @var LineItemSubscriptionRule
     */
    private $rule;


    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->rule = new LineItemSubscriptionRule();
    }

    /**
     * @return void
     */
    public function testGetName(): void
    {
        static::assertSame('mollie_lineitem_subscription_rule', $this->rule->getName());
    }

    /**
     * @return void
     */
    public function testGetConstraints(): void
    {
        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('isSubscription', $ruleConstraints, 'Rule Constraint isSubscription is not defined');
    }

    /**
     * This test verifies that our rule works on NON subscription items.
     * We first try to find NON-subscription item (valid), and then
     * try to find a subscription item (error).
     *
     * @testWith        [true, false]
     *                  [false, true]
     * @return void
     */
    public function testNonSubscriptionCart(bool $expected, bool $lookingForSubscription): void
    {
        $lineItem = new LineItem('', 'product');

        $this->rule->assign([
            'isSubscription' => $lookingForSubscription
        ]);

        $match = $this->rule->match(
            new LineItemScope(
                $lineItem,
                $this->createMock(SalesChannelContext::class)
            )
        );

        static::assertSame($expected, $match);
    }

    /**
     * This test verifies that our rule works on subscription items.
     * We first try to find subscription item (valid), and then
     * try to find a non-subscription item (error).
     *
     * @testWith        [true, true]
     *                  [false, false]
     * @return void
     */
    public function testSubscriptionCart(bool $expected, bool $lookingForSubscription): void
    {
        $lineItem = new LineItem('', 'product');

        $lineItemAttributes = new LineItemAttributes($lineItem);
        $lineItemAttributes->setSubscriptionProduct(true);
        $lineItem->setPayload([
            'customFields' => $lineItemAttributes->toArray(),
        ]);

        # just verify it's really a subscription now
        $lineItemAttributes = new LineItemAttributes($lineItem);
        static::assertSame(true, $lineItemAttributes->isSubscriptionProduct(), 'item is not a subscription item');

        $this->rule->assign([
            'isSubscription' => $lookingForSubscription
        ]);

        $match = $this->rule->match(
            new LineItemScope(
                $lineItem,
                $this->createMock(SalesChannelContext::class)
            )
        );

        static::assertSame($expected, $match);
    }
}
