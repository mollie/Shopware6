<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Service\Cart\CartBackupService;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ExpressCartRestoreSubscriber implements EventSubscriberInterface
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
            CheckoutFinishPageLoadedEvent::class => 'onRestoreBackup',
        ];
    }

    public function onRestoreBackup(PageLoadedEvent $event): void
    {
        $context = $event->getSalesChannelContext();

        if ($this->cartBackupService->isBackupExisting($context)) {
            $this->cartBackupService->restoreCart($context);
            $this->cartBackupService->clearBackup($context);
        }
    }
}
