<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;


use Kiener\MolliePayments\Event\PaymentPageFailEvent;
use Kiener\MolliePayments\Event\PaymentPageRedirectEvent;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Session;


class OrderRepeaterSubscriber implements EventSubscriberInterface
{
    const ORDER_ID_SESSION_KEY = 'sw-last-created-order';
    /**
     * @var Session
     */
    private $session;
    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var OrderConverter
     */
    private $orderConverter;
    /**
     * @var CartPersister
     */
    private $cartPersister;

    public function __construct(Session $session, EntityRepositoryInterface $orderRepository, OrderConverter $orderConverter, CartPersister $cartPersister, LoggerInterface $logger)
    {
        $this->session = $session;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
        $this->orderConverter = $orderConverter;
        $this->cartPersister = $cartPersister;
    }

    public static function getSubscribedEvents()
    {
        return [
            CheckoutOrderPlacedEvent::class => 'onOrderCreated',
            CheckoutCartPageLoadedEvent::class => 'onShowCartPage',
            PaymentPageRedirectEvent::EVENT_NAME => 'clearSession',
            PaymentPageFailEvent::EVENT_NAME => 'clearSession'
        ];
    }

    public function clearSession()
    {
        $this->session->remove(self::ORDER_ID_SESSION_KEY);
    }

    public function onOrderCreated(CheckoutOrderPlacedEvent $event)
    {
        $this->session->set(self::ORDER_ID_SESSION_KEY, $event->getOrder()->getId());
    }

    public function onShowCartPage(CheckoutCartPageLoadedEvent $event)
    {
        $lastOrderId = $this->session->get(self::ORDER_ID_SESSION_KEY);
        if (!$lastOrderId) {
            return;
        }
        $criteria = new Criteria([$lastOrderId]);
        $criteria->addAssociation('deliveries');
        $criteria->addAssociation('deliveries.shippingMethod');
        $criteria->addAssociation('deliveries.shippingOrderAddress');
        $criteria->addAssociation('deliveries.shippingOrderAddress.country');
        $criteria->addAssociation('deliveries.positions');
        $criteria->addAssociation('deliveries.positions.orderLineItem');
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('country');
        $criteria->addAssociation('transactions');

        $searchResult = $this->orderRepository->search($criteria, $event->getContext());
        if ($searchResult->count() === 0) {
            $this->logger->warning('Failed to find orderID by session, deleting the sessionId', ['lastOrderId' => $lastOrderId]);
            $this->session->remove(self::ORDER_ID_SESSION_KEY);

            return;
        }

        /** @var OrderEntity $orderEntity */
        $orderEntity = $searchResult->first();

        $cart = $this->orderConverter->convertToCart($orderEntity, $event->getContext());
        /** @var SalesChannelContext $salesChannelContext */
        $salesChannelContext = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        $cart->setToken($salesChannelContext->getToken());

        $this->cartPersister->save($cart, $salesChannelContext);

        $this->session->remove(self::ORDER_ID_SESSION_KEY);
        $event->getPage()->setCart($cart);

    }

}
