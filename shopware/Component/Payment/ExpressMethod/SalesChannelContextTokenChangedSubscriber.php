<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressMethod;

use Shopware\Core\System\SalesChannel\Event\SalesChannelContextTokenChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class SalesChannelContextTokenChangedSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly CartBackupService $cartBackupService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SalesChannelContextTokenChangeEvent::class => 'onTokenChange',
        ];
    }

    public function onTokenChange(SalesChannelContextTokenChangeEvent $event): void
    {
        $this->cartBackupService->replaceToken(
            $event->getPreviousToken(),
            $event->getCurrentToken(),
            $event->getSalesChannelContext()
        );
    }
}
