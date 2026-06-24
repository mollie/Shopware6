<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Action;

use Mollie\Shopware\Component\Subscription\Action\AbstractAction;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionCancelledEvent;
use Mollie\Shopware\Unit\Fake\ConcreteAction;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;

#[CoversClass(AbstractAction::class)]
final class AbstractActionTest extends TestCase
{
    public function testSupportsReturnsTrueWhenActionNameMatchesStaticActionName(): void
    {
        $action = new ConcreteAction();

        $this->assertTrue($action->supports('concrete-test-action'));
    }

    public function testSupportsReturnsFalseWhenActionNameDoesNotMatch(): void
    {
        $action = new ConcreteAction();

        $this->assertFalse($action->supports('other-name'));
    }

    public function testGetEventInstantiatesConfiguredEventClassWithArguments(): void
    {
        $action = new ConcreteAction();
        $subscription = SubscriptionEntityBuilder::create()->withId('subscription-id')->build();
        $customer = new CustomerEntity();
        $context = Context::createDefaultContext();

        $event = $action->getEvent($subscription, $customer, $context);

        $this->assertInstanceOf(SubscriptionCancelledEvent::class, $event);
        $this->assertSame($subscription, $event->getSubscription());
        $this->assertSame($customer, $event->getCustomer());
        $this->assertSame($context, $event->getContext());
    }
}
