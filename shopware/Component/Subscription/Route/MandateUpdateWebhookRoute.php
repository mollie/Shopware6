<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Mollie\Shopware\Component\Subscription\Action\UpdatePaymentMethodAction;
use Mollie\Shopware\Component\Subscription\SubscriptionDataService;
use Mollie\Shopware\Component\Subscription\SubscriptionDataServiceInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => false, 'auth_enabled' => false])]
final class MandateUpdateWebhookRoute
{
    public function __construct(
        #[Autowire(service: SubscriptionDataService::class)]
        private readonly SubscriptionDataServiceInterface $subscriptionDataService,
        private readonly UpdatePaymentMethodAction $action,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route(
        path: '/api/mollie/webhook/subscription/{subscriptionId}/mandate/update',
        name: 'api.mollie.webhook.subscription.mandate.update',
        methods: ['GET', 'POST']
    )]
    public function update(string $subscriptionId, Context $context): JsonResponse
    {
        $subscriptionId = strtolower($subscriptionId);

        $this->logger->info('Subscription mandate update webhook received', [
            'subscriptionId' => $subscriptionId,
        ]);

        try {
            $subscriptionData = $this->subscriptionDataService->findById($subscriptionId, $context);
            $subscription = $subscriptionData->getSubscription();
            $orderNumber = (string) $subscriptionData->getOrder()->getOrderNumber();

            $this->action->confirm($subscription, $orderNumber, $context);

            $this->logger->info('Subscription mandate update webhook processed', [
                'subscriptionId' => $subscriptionId,
            ]);

            return new JsonResponse(['success' => true]);
        } catch (\Throwable $e) {
            $this->logger->error('Subscription mandate update webhook failed', [
                'subscriptionId' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }
}
