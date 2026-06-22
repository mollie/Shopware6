<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Settings;

use Mollie\Shopware\Component\Settings\SystemConfigSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;

#[CoversClass(SystemConfigSubscriber::class)]
final class SystemConfigSubscriberTest extends TestCase
{
    public function testSubscribesToSystemConfigChangedEvent(): void
    {
        $events = SystemConfigSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(SystemConfigChangedEvent::class, $events);
    }

    public function testHandlesProfileIdAndApplePayDownload(): void
    {
        $listeners = SystemConfigSubscriber::getSubscribedEvents()[SystemConfigChangedEvent::class];

        $methods = array_map(static function (array $listener): string {
            return $listener[0];
        }, $listeners);

        self::assertContains('updateProfileId', $methods);
        self::assertContains('downloadApplePayDomainAssociationFile', $methods);
    }
}
