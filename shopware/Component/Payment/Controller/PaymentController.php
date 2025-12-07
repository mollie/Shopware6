<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Controller;

use Mollie\Shopware\Component\Payment\Route\AbstractReturnRoute;
use Mollie\Shopware\Component\Payment\Route\AbstractWebhookRoute;
use Mollie\Shopware\Component\Payment\Route\ReturnRoute;
use Mollie\Shopware\Component\Payment\Route\WebhookRoute;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Payment\Controller\PaymentController as ShopwarePaymentController;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront'], 'csrf_protected' => false])]
final class PaymentController extends AbstractController
{
    public function __construct(
        #[Autowire(service: ReturnRoute::class)]
        private AbstractReturnRoute $returnRoute,
        #[Autowire(service: WebhookRoute::class)]
        private AbstractWebhookRoute $webhookRoute,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/mollie/payment/{transactionId}', name: 'frontend.mollie.payment', methods: ['GET', 'POST'], options: ['seo' => false])]
    public function return(string $transactionId, Context $context): Response
    {
        $this->logger->info('Returning from Payment Provider', [
            'transactionId' => $transactionId,
        ]);
        $response = $this->returnRoute->return($transactionId, $context);
        $paymentStatus = $response->getPaymentStatus();

        if ($paymentStatus->isFailed()) {
            // TODO mollie failure mode
        }
        $query = (string) parse_url($response->getFinalizeUrl(), PHP_URL_QUERY);
        $queryParameters = [];
        parse_str($query, $queryParameters);
        $this->logger->info('Call shopware finalize transaction', [
            'transactionId' => $transactionId,
        ]);
        $controller = sprintf('%s::%s', ShopwarePaymentController::class, 'finalizeTransaction');

        return $this->forward($controller, [], $queryParameters);
    }

    #[Route(path: '/mollie/webhook/{transactionId}', name: 'frontend.mollie.webhook', methods: ['GET', 'POST'], options: ['seo' => false])]
    public function webhook(string $transactionId, Context $context): Response
    {
        try {
            $this->logger->info('Webhook received', [
                'transactionId' => $transactionId,
            ]);
            $response = $this->webhookRoute->notify($transactionId, $context);

            return new JsonResponse($response->getObject());
        } catch (ShopwareHttpException $exception) {
            $this->logger->warning(
                'Webhook request failed with warning',
                [
                    'transactionId' => $transactionId,
                    'message' => $exception->getMessage(),
                ]
            );

            return new JsonResponse(['success' => false, 'error' => $exception->getMessage()], $exception->getStatusCode());
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Webhook request failed',
                [
                    'transactionId' => $transactionId,
                    'message' => $exception->getMessage(),
                ]
            );

            return new JsonResponse(['success' => false, 'error' => $exception->getMessage()], 422);
        }
    }
}
