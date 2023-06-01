<?php

namespace MolliePayments\Tests\Components\Subscription\Rule;

use Kiener\MolliePayments\Components\Subscription\Rule\CartSubscriptionRule;
use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartSubscriptionRuleTest extends TestCase
{
    /**
     * @var CartSubscriptionRule
     */
    private $rule;


    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->rule = new CartSubscriptionRule();
    }

    /**
     * @return void
     */
    public function testGetName(): void
    {
        static::assertSame('mollie_cart_subscription_rule', $this->rule->getName());
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
     * This test verifies that our rule works on NON subscription carts.
     * We first try to find NON-subscription carts (valid), and then
     * try to find a subscription cart (error).
     *
     * @testWith        [true, false]
     *                  [false, true]
     * @return void
     */
    public function testNonSubscriptionCart(bool $expected, bool $lookingForSubscription): void
    {
        $cart = new Cart('', '');
        $lineItem = new LineItem('', 'product');

        $cart->setLineItems(new LineItemCollection([$lineItem]));


        $this->rule->assign([
            'isSubscription' => $lookingForSubscription
        ]);

        $match = $this->rule->match(
            new CartRuleScope(
                $cart,
                $this->createMock(SalesChannelContext::class)
            )
        );

        static::assertSame($expected, $match);
    }

    /**
     * This test verifies that our rule works on subscription carts.
     * We first try to find subscription carts (valid), and then
     * try to find a non-subscription cart (error).
     *
     * @testWith        [true, true]
     *                  [false, false]
     * @return void
     */
    public function testSubscriptionCart(bool $expected, bool $lookingForSubscription): void
    {
        $cart = new Cart('', '');

        $lineItem = new LineItem('', 'product');

        $lineItemAttributes = new LineItemAttributes($lineItem);
        $lineItemAttributes->setSubscriptionProduct(true);
        $lineItem->setPayload([
            'customFields' => $lineItemAttributes->toArray(),
        ]);

        # just verify it's really a subscription now
        $lineItemAttributes = new LineItemAttributes($lineItem);
        static::assertSame(true, $lineItemAttributes->isSubscriptionProduct(), 'item is not a subscription item');


        $cart->setLineItems(new LineItemCollection([$lineItem]));

        $this->rule->assign([
            'isSubscription' => $lookingForSubscription
        ]);

        $match = $this->rule->match(
            new CartRuleScope(
                $cart,
                $this->createMock(SalesChannelContext::class)
            )
        );

        static::assertSame($expected, $match);
    }
}
