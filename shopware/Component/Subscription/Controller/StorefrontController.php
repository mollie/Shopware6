<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Controller;

use Mollie\Shopware\Component\Subscription\Route\AbstractWebhookRoute;
use Mollie\Shopware\Component\Subscription\Route\WebhookRoute;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID], 'csrf_protected' => false])]
final class StorefrontController extends AbstractController
{
    public function __construct(
        #[Autowire(service: WebhookRoute::class)]
        private readonly AbstractWebhookRoute $webhookRoute,
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
}
