<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Event;

use Mollie\Shopware\Component\Mollie\CreateSubscription;
use Mollie\Shopware\Component\Mollie\Interval;
use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Subscription\Event\ModifyCreateSubscriptionPayloadEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

#[CoversClass(ModifyCreateSubscriptionPayloadEvent::class)]
final class ModifyCreateSubscriptionPayloadEventTest extends TestCase
{
    public function testGettersReturnConstructorArguments(): void
    {
        $createSubscription = new CreateSubscription(
            'description',
            new Interval(1, IntervalUnit::MONTHS),
            new Money(10.00, 'EUR'),
        );
        $context = Context::createDefaultContext();

        $event = new ModifyCreateSubscriptionPayloadEvent($createSubscription, $context);

        $this->assertSame($createSubscription, $event->getCreateSubscription());
        $this->assertSame($context, $event->getContext());
    }
}
