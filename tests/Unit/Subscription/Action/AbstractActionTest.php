<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Action;

use Mollie\Shopware\Component\Mollie\Subscription;
use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\Action\AbstractAction;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionCancelledEvent;
use Mollie\Shopware\Component\Subscription\SubscriptionDataStruct;
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
        $action = new ConcreteActionForAbstractActionTest();

        $this->assertTrue($action->supports('concrete-test-action'));
    }

    public function testSupportsReturnsFalseWhenActionNameDoesNotMatch(): void
    {
        $action = new ConcreteActionForAbstractActionTest();

        $this->assertFalse($action->supports('other-name'));
    }

    public function testGetEventInstantiatesConfiguredEventClassWithArguments(): void
    {
        $action = new ConcreteActionForAbstractActionTest();
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

/**
 * Minimal AbstractAction implementation that does not override supports() or
 * getEvent() so the parent behaviour is exercised through static::getActioName().
 */
final class ConcreteActionForAbstractActionTest extends AbstractAction
{
    public function execute(SubscriptionDataStruct $subscriptionData, SubscriptionSettings $settings, Subscription $mollieSubscription, string $orderNumber, Context $context): Subscription
    {
        throw new \LogicException('execute() is not exercised in AbstractActionTest');
    }

    public function getEventClass(): string
    {
        return SubscriptionCancelledEvent::class;
    }

    public static function getActioName(): string
    {
        return 'concrete-test-action';
    }
}
