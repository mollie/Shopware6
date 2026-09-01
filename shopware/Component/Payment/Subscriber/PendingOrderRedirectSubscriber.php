<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Subscriber;

use Mollie\Shopware\Component\Payment\Action\Pay;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class PendingOrderRedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RouterInterface $router,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onController',
        ];
    }

    public function onController(ControllerEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        // Bail out before touching the session on unrelated routes. Accessing the
        // session triggers session_start() and its exclusive lock, which would
        // serialize every main request (incl. Admin API) behind the session lock.
        if ($route !== 'frontend.mollie.payment' && $route !== 'frontend.account.order.page') {
            return;
        }

        if (! $request->hasSession()) {
            return;
        }

        $pendingOrderId = (string) $request->getSession()->get(Pay::SESSION_KEY_PENDING_ORDER);

        if (strlen($pendingOrderId) === 0) {
            return;
        }

        // When the customer returns from Mollie (success or failure), clear the
        // session key so a later visit to the order list does not trigger a redirect.
        if ($route === 'frontend.mollie.payment') {
            $request->getSession()->remove(Pay::SESSION_KEY_PENDING_ORDER);
            $this->logger->debug('[PendingOrderRedirect] cleared session key on mollie return');

            return;
        }

        $request->getSession()->remove(Pay::SESSION_KEY_PENDING_ORDER);

        $editUrl = $this->router->generate('frontend.account.edit-order.page', [
            'orderId' => $pendingOrderId,
        ]);

        $this->logger->debug('[PendingOrderRedirect] redirecting to edit-order', [
            'orderId' => $pendingOrderId,
            'url' => $editUrl,
        ]);

        $event->setController(function () use ($editUrl) {
            return new RedirectResponse($editUrl);
        });
    }
}
