<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Rule;

use Mollie\Shopware\Component\Subscription\Rule\CartSubscriptionRule;
use Mollie\Shopware\Entity\Product\Product;
use Mollie\Shopware\Mollie;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[CoversClass(CartSubscriptionRule::class)]
final class CartSubscriptionRuleTest extends TestCase
{
    private CartSubscriptionRule $rule;

    protected function setUp(): void
    {
        $this->rule = new CartSubscriptionRule();
    }

    public function testGetName(): void
    {
        $this->assertSame('mollie_cart_subscription_rule', $this->rule->getName());
    }

    public function testGetConstraintsExposesIsSubscription(): void
    {
        $this->assertArrayHasKey('isSubscription', $this->rule->getConstraints());
    }

    #[TestWith([true, false])]
    #[TestWith([false, true])]
    public function testNonSubscriptionCart(bool $expected, bool $lookingForSubscription): void
    {
        $cart = new Cart('cart-token');
        $cart->setLineItems(new LineItemCollection([new LineItem('line-id', 'product')]));

        $this->rule->assign(['isSubscription' => $lookingForSubscription]);

        $match = $this->rule->match(
            new CartRuleScope($cart, $this->createMock(SalesChannelContext::class))
        );

        $this->assertSame($expected, $match);
    }

    #[TestWith([true, true])]
    #[TestWith([false, false])]
    public function testSubscriptionCart(bool $expected, bool $lookingForSubscription): void
    {
        $extension = new Product();
        $extension->setIsSubscription(true);

        $lineItem = new LineItem('line-id', 'product');
        $lineItem->addExtension(Mollie::EXTENSION, $extension);

        $cart = new Cart('cart-token');
        $cart->setLineItems(new LineItemCollection([$lineItem]));

        $this->rule->assign(['isSubscription' => $lookingForSubscription]);

        $match = $this->rule->match(
            new CartRuleScope($cart, $this->createMock(SalesChannelContext::class))
        );

        $this->assertSame($expected, $match);
    }
}
