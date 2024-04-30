<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class KernelSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onModifyRouteScope'
        ];
    }

    /**
     * the route scopes are added as array to routes.xml in SW 6.4 those are inside RouteScope class. so we convert our array to class
     * @param ControllerEvent $event
     * @return void
     */
    public function onModifyRouteScope(ControllerEvent $event): void
    {
        if (! class_exists(RouteScope::class)) {
            return;
        }

        $attributes = $event->getRequest()->attributes;

        /** @var null|array<string>|RouteScope $routeScope */
        $routeScope = $attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE);

        if ($routeScope === null) {
            return;
        }
        if ($routeScope instanceof RouteScope) {
            return;
        }

        $routeScope = new RouteScope(['scopes' => $routeScope]);
        $attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, $routeScope);
    }
}
