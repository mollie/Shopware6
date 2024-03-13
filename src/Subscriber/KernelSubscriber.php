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

    public function onModifyRouteScope(ControllerEvent $event): void
    {
        $attributes = $event->getRequest()->attributes;

        /** @var null|RouteScope|string $routeScopeValue */
        $routeScopeValue = $attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE);

        if (is_string($routeScopeValue)) {
            $routeScope = [$routeScopeValue];
            
            if (class_exists(RouteScope::class)) {
                $routeScope = new RouteScope(['scopes' => [$routeScopeValue]]);
            }

            $attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, $routeScope);
        }
    }
}
