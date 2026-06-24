<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Mollie\Shopware\Component\Subscription\SubscriptionActionHandler;
use Mollie\Shopware\Component\Subscription\SubscriptionActionHandlerInterface;
use Mollie\Shopware\Component\Subscription\SubscriptionDataService;
use Mollie\Shopware\Component\Subscription\SubscriptionDataServiceInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['store-api']])]
final class ChangeStateRoute extends AbstractChangeStateRoute
{
    public function __construct(
        #[Autowire(service: SubscriptionDataService::class)]
        private readonly SubscriptionDataServiceInterface $subscriptionDataService,
        #[Autowire(service: SubscriptionActionHandler::class)]
        private readonly SubscriptionActionHandlerInterface $actionHandler
    ) {
    }

    public function getDecorated(): AbstractChangeStateRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/mollie/subscription/{subscriptionId}/pause',
        name: 'store-api.mollie.subscription.pause',
        defaults: ['_loginRequired' => true, 'action' => 'pause'],
        methods: ['POST']
    )]
    #[Route(
        path: '/store-api/mollie/subscription/{subscriptionId}/resume',
        name: 'store-api.mollie.subscription.resume',
        defaults: ['_loginRequired' => true, 'action' => 'resume'],
        methods: ['POST']
    )]
    #[Route(
        path: '/store-api/mollie/subscription/{subscriptionId}/skip',
        name: 'store-api.mollie.subscription.skip',
        defaults: ['_loginRequired' => true, 'action' => 'skip'],
        methods: ['POST']
    )]
    #[Route(
        path: '/store-api/mollie/subscription/{subscriptionId}/cancel',
        name: 'store-api.mollie.subscription.cancel',
        defaults: ['_loginRequired' => true, 'action' => 'cancel'],
        methods: ['POST']
    )]
    public function changeState(string $subscriptionId, Request $request, SalesChannelContext $context): ChangeStateResponse
    {
        $customer = $context->getCustomer();
        if (! $customer instanceof CustomerEntity) {
            throw ChangeStateException::notAuthenticated();
        }

        $subscriptionId = strtolower($subscriptionId);
        $action = (string) $request->attributes->get('action');

        $subscriptionData = $this->subscriptionDataService->findById($subscriptionId, $context->getContext());
        if ($subscriptionData->getSubscription()->getCustomerId() !== $customer->getId()) {
            throw ChangeStateException::notOwner($subscriptionId);
        }

        $this->actionHandler->handle($action, $subscriptionId, $context->getContext());

        return new ChangeStateResponse($subscriptionId, $action);
    }
}
