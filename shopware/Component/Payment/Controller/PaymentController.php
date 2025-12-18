<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Controller;

use Mollie\Shopware\Component\FailureMode\PaymentPageFailedEvent;
use Mollie\Shopware\Component\Payment\Route\AbstractReturnRoute;
use Mollie\Shopware\Component\Payment\Route\AbstractWebhookRoute;
use Mollie\Shopware\Component\Payment\Route\ReturnRoute;
use Mollie\Shopware\Component\Payment\Route\WebhookRoute;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Controller\PaymentController as ShopwarePaymentController;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront'], 'csrf_protected' => false])]
final class PaymentController extends StorefrontController
{
    public function __construct(
        #[Autowire(service: ReturnRoute::class)]
        private AbstractReturnRoute $returnRoute,
        #[Autowire(service: WebhookRoute::class)]
        private AbstractWebhookRoute $webhookRoute,
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: 'event_dispatcher')]
        private EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/mollie/payment/{transactionId}', name: 'frontend.mollie.payment', methods: ['GET', 'POST'], options: ['seo' => false])]
    public function return(string $transactionId, SalesChannelContext $salesChannelContext): Response
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $logData = [
            'transactionId' => $transactionId,
            'salesChannelId' => $salesChannelId,
        ];
        $this->logger->info('Returning from Payment Provider', $logData);
        $response = $this->returnRoute->return($transactionId, $salesChannelContext->getContext());
        $paymentStatus = $response->getPaymentStatus();
        $payment = $response->getPayment();

        $paymentSettings = $this->settingsService->getPaymentSettings($salesChannelId);
        $logData['paymentStatus'] = $paymentStatus->value;
        $logData['paymentId'] = $payment->getId();
        $orderTransaction = $payment->getShopwareTransaction();
        $shopwareOrder = $orderTransaction->getOrder();

        if ($shopwareOrder instanceof OrderEntity && $paymentStatus->isFailed() && ! $paymentSettings->isShopwareFailedPayment()) {
            $paymentFailedEvent = new PaymentPageFailedEvent(
                $transactionId,
                $shopwareOrder,
                $payment,
                $salesChannelContext
            );
            $this->logger->info('Payment failed, send PaymentPageFailedEvent in mollie failure mode', $logData);
            $this->eventDispatcher->dispatch($paymentFailedEvent);
        }

        $query = (string) parse_url($response->getFinalizeUrl(), PHP_URL_QUERY);
        $queryParameters = [];
        parse_str($query, $queryParameters);
        $this->logger->info('Call shopware finalize transaction', $logData);
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
