<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Subscriber;

use Mollie\Shopware\Component\Payment\Subscriber\CancelOrderSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CancelOrderSubscriber::class)]
final class CancelOrderSubscriberTest extends TestCase
{
    public function testListeningOnCorrectEvent(): void
    {
        $this->assertArrayHasKey('state_machine.order.state_changed', CancelOrderSubscriber::getSubscribedEvents());
    }
}
