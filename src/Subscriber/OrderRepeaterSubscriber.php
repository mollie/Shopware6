<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;


use Kiener\MolliePayments\Event\PaymentPageFailEvent;
use Kiener\MolliePayments\Event\PaymentPageRedirectEvent;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Session;


class OrderRepeaterSubscriber extends AbstractController implements EventSubscriberInterface
{
    const ORDER_ID_SESSION_KEY = 'sw-last-created-order';
    /**
     * @var Session
     */
    private $session;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(Session $session, LoggerInterface $logger)
    {
        $this->session = $session;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return [
            CheckoutOrderPlacedEvent::class => 'onOrderCreated',
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


}
