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

        /** @var null|RouteScope|string $routeScope */
        $routeScope = $attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE);

        if (is_string($routeScope)) {
            $routeData = [$routeScope];
            
            if (class_exists(RouteScope::class)) {
                $routeData = new RouteScope(['scopes' => [$routeScope]]);
            }

            $attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, $routeData);
        }
    }
}
