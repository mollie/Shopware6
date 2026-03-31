<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Mollie\Shopware\Component\Payment\PayAction;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class PendingOrderRedirectSubscriber implements EventSubscriberInterface
{
    private RouterInterface $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
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

        if ($route !== 'frontend.account.order.page') {
            return;
        }

        if (! $request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        $pendingOrderId = $session->get(PayAction::SESSION_KEY_PENDING_ORDER);

        if (empty($pendingOrderId)) {
            return;
        }

        $session->remove(PayAction::SESSION_KEY_PENDING_ORDER);

        $editUrl = $this->router->generate('frontend.account.edit-order.page', [
            'orderId' => $pendingOrderId,
        ]);

        $event->setController(function () use ($editUrl) {
            return new RedirectResponse($editUrl);
        });
    }
}
