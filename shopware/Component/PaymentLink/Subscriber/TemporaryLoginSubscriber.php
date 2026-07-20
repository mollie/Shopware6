<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\PaymentLink\Subscriber;

use Mollie\Shopware\Component\PaymentLink\Controller\PaymentLinkController;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLogoutRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\LogoutRoute;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A payment link logs the order's customer in so the native checkout finish page can be shown. That
 * login is only temporary: once the finish page has loaded we log the customer out again, so anyone
 * who opens the link does not stay logged into the customer's account. The current request still
 * renders the finish page fully (the logout only affects the session token for the next request).
 */
final class TemporaryLoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: LogoutRoute::class)]
        private readonly AbstractLogoutRoute $logoutRoute,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutFinishPageLoadedEvent::class => 'onFinishPageLoaded',
        ];
    }

    public function onFinishPageLoaded(CheckoutFinishPageLoadedEvent $event): void
    {
        $session = $event->getRequest()->getSession();
        if ($session->get(PaymentLinkController::TEMPORARY_LOGIN_SESSION_KEY) !== true) {
            return;
        }

        $session->remove(PaymentLinkController::TEMPORARY_LOGIN_SESSION_KEY);

        $this->logger->info('Logging out the temporary payment link login after the finish page');

        $emptyDataBag = new RequestDataBag();
        $this->logoutRoute->logout($event->getSalesChannelContext(), $emptyDataBag);
    }
}
