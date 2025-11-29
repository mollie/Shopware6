<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Service\Cart\CartBackupService;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextTokenChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SalesChannelContextTokenChangedSubscriber implements EventSubscriberInterface
{
    /**
     * @var CartBackupService
     */
    private $cartBackupService;

    public function __construct(CartBackupService $cartBackupService)
    {
        $this->cartBackupService = $cartBackupService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SalesChannelContextTokenChangeEvent::class => 'onTokenChange',
        ];
    }

    public function onTokenChange(SalesChannelContextTokenChangeEvent $event): void
    {
        $this->cartBackupService->replaceToken($event->getPreviousToken(), $event->getCurrentToken(), $event->getSalesChannelContext());
    }
}
