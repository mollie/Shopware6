<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Service\Order\OrderExpireService;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderEditSubscriber implements EventSubscriberInterface
{
    /**
     * @var OrderExpireService
     */
    private $orderExpireService;

    public function __construct(
        OrderExpireService $orderExpireService,
    ) {
        $this->orderExpireService = $orderExpireService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AccountOrderPageLoadedEvent::class => 'accountOrderDetailPageLoaded',
        ];
    }

    public function accountOrderDetailPageLoaded(AccountOrderPageLoadedEvent $event): void
    {
        /** @var OrderCollection $orders */
        $orders = $event->getPage()->getOrders()->getEntities();

        $context = $event->getContext();

        $this->orderExpireService->cancelExpiredOrders($orders, $context);
    }
}
