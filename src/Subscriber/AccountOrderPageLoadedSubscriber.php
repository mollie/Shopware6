<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Components\RefundManager\RefundManagerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AccountOrderPageLoadedSubscriber implements EventSubscriberInterface
{
    /**
     * @var RefundManagerInterface
     */
    private $refundManager;

    /**
     *
     */
    public function __construct(RefundManagerInterface $refundManager)
    {
        $this->refundManager = $refundManager;
    }

    /**
     * @return array<mixed>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            AccountOrderPageLoadedEvent::class => 'onAccountOrderPageLoaded'
        ];
    }

    /**
     * @param AccountOrderPageLoadedEvent $event
     * @return void
     */
    public function onAccountOrderPageLoaded(AccountOrderPageLoadedEvent $event)
    {
        # Add the refunds for the order history details page
        $orders = $event->getPage()->getOrders();
        /** @var OrderEntity $order */
        foreach ($orders as $order) {
           $data =  $this->refundManager->getData($order,$event->getContext());
            $order->addArrayExtension("mollie_refunds",$data->toArray());
        }

    }
}
