<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription;

use Mollie\Shopware\Component\Subscription\SubscriptionGroupCart;
use Mollie\Shopware\Unit\Builder\CartBuilder;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubscriptionGroupCart::class)]
final class SubscriptionGroupCartTest extends TestCase
{
    public function testGettersReturnConstructorArguments(): void
    {
        $cart = CartBuilder::create()->build();
        $context = new FakeSalesChannelContext();

        $groupCart = new SubscriptionGroupCart($cart, $context);

        $this->assertSame($cart, $groupCart->getCart());
        $this->assertSame($context, $groupCart->getSalesChannelContext());
    }
}
