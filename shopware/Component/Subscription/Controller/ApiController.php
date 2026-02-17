<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Controller;

use Mollie\Shopware\Component\Subscription\SubscriptionActionHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
final class ApiController extends AbstractController
{
    public function __construct(
        private readonly SubscriptionActionHandler $actionHandler
    ) {
    }

    #[Route(path: '/api/_action/mollie/subscriptions/pause', name: 'api.action.mollie.subscription.pause', defaults: ['action' => 'pause'], methods: ['POST'])]
    #[Route(path: '/api/_action/mollie/subscriptions/resume', name: 'api.action.mollie.subscription.resume', defaults: ['action' => 'resume'], methods: ['POST'])]
    #[Route(path: '/api/_action/mollie/subscriptions/skip', name: 'api.action.mollie.subscription.skip', defaults: ['action' => 'skip'], methods: ['POST'])]
    #[Route(path: '/api/_action/mollie/subscriptions/cancel', name: 'api.action.mollie.subscription.cancel', defaults: ['action' => 'cancel'], methods: ['POST'])]
    #[Route(path: '/api/_action/mollie/subscriptions/{action}', name: 'api.action.mollie.subscription.changeState', methods: ['POST'])]
    public function changeState(Request $request, Context $context): Response
    {
        $subscriptionId = (string) $request->request->get('id');
        $action = $request->attributes->get('action');

        $response = $this->actionHandler->handle($action, $subscriptionId, $context);

        return new JsonResponse([]);
    }
}
