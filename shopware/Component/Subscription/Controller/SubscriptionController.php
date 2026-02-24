<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Controller;

use Mollie\Shopware\Component\Subscription\Route\AbstractWebhookRoute;
use Mollie\Shopware\Component\Subscription\Route\WebhookRoute;
use Mollie\Shopware\Component\Subscription\SubscriptionActionHandler;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID], 'csrf_protected' => false])]
final class SubscriptionController extends StorefrontController
{
    public function __construct(
        #[Autowire(service: WebhookRoute::class)]
        private readonly AbstractWebhookRoute $webhookRoute,
        private readonly SubscriptionActionHandler $actionHandler,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route(path: '/mollie/webhook/subscription/{subscriptionId}', name: 'frontend.mollie.webhook.subscription', options: ['seo' => false], methods: ['GET', 'POST'])]
    public function webhook(string $subscriptionId, Request $request, SalesChannelContext $context): Response
    {
        try {
            $this->logger->debug('Subscription Webhook received', [
                'transactionId' => $subscriptionId,
            ]);
            $response = $this->webhookRoute->notify($subscriptionId, $request,$context->getContext());

            return new JsonResponse($response->getObject());
        } catch (ShopwareHttpException $exception) {
            $this->logger->warning(
                'Subscription Webhook request failed with warning',
                [
                    'transactionId' => $subscriptionId,
                    'message' => $exception->getMessage(),
                ]
            );

            return new JsonResponse(['success' => false, 'error' => $exception->getMessage()], $exception->getStatusCode());
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Subscription Webhook request failed',
                [
                    'transactionId' => $subscriptionId,
                    'message' => $exception->getMessage(),
                ]
            );

            return new JsonResponse(['success' => false, 'error' => $exception->getMessage()], 422);
        }
    }

    #[Route(path: '/account/mollie/subscriptions/{subscriptionId}/pause', name: 'frontend.account.mollie.subscriptions.pause', defaults: ['action' => 'pause'],methods: ['POST'])]
    #[Route(path: '/account/mollie/subscriptions/{subscriptionId}/resume', name: 'frontend.account.mollie.subscriptions.resume', defaults: ['action' => 'resume'],methods: ['POST'])]
    #[Route(path: '/account/mollie/subscriptions/{subscriptionId}/skip', name: 'frontend.account.mollie.subscriptions.skip', defaults: ['action' => 'skip'],methods: ['POST'])]
    #[Route(path: '/account/mollie/subscriptions/{subscriptionId}/cancel', name: 'frontend.account.mollie.subscriptions.cancel', defaults: ['action' => 'cancel'],methods: ['POST'])]
    #[Route(path: '/account/mollie/subscriptions/{subscriptionId}/{action}', name: 'frontend.account.mollie.subscriptions.changeState', methods: ['POST'])]
    public function changeState(string $subscriptionId, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        if ($salesChannelContext->getCustomer() === null) {
            return $this->redirectToRoute('frontend.account.login.page');
        }

        $action = $request->attributes->get('action');

        $translationKey = 'molliePayments.subscriptions.account.%s%s';

        try {
            $this->actionHandler->handle($action, $subscriptionId, $salesChannelContext->getContext());
            $translationKey = sprintf($translationKey, 'success', ucfirst($action));
            $this->addFlash(self::SUCCESS, $this->trans($translationKey));
        } catch (\Throwable $exception) {
            $translationKey = sprintf($translationKey, 'error', ucfirst($action));
            $this->logger->error('Error when changing subscription state', [
                'subscriptionId' => $subscriptionId,
                'action' => $action,
                'message' => $exception->getMessage(),
            ]);

            $this->addFlash(self::DANGER, $this->trans($translationKey));
        }

        return $this->redirectToRoute('frontend.account.mollie.subscriptions.page');
    }
}
