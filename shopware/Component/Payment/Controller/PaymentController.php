<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Controller;

use Mollie\Shopware\Component\FailureMode\PaymentPageFailedEvent;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Payment\Route\AbstractWebhookRoute;
use Mollie\Shopware\Component\Payment\Route\WebhookRoute;
use Mollie\Shopware\Component\Payment\Token\PaymentTokenRepository;
use Mollie\Shopware\Component\Payment\Token\PaymentTokenRepositoryInterface;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Controller\PaymentController as ShopwarePaymentController;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
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
        #[Autowire(service: MollieGateway::class)]
        private MollieGatewayInterface $mollieGateway,
        #[Autowire(service: WebhookRoute::class)]
        private AbstractWebhookRoute $webhookRoute,
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: 'event_dispatcher')]
        private EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: PaymentTokenRepository::class)]
        private PaymentTokenRepositoryInterface $paymentTokenRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/mollie/payment/{transactionId}', name: 'frontend.mollie.payment', methods: ['GET', 'POST'], options: ['seo' => false])]
    public function return(string $transactionId, SalesChannelContext $salesChannelContext): Response
    {
        $transactionId = strtolower($transactionId);
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $logData = [
            'transactionId' => $transactionId,
            'salesChannelId' => $salesChannelId,
        ];
        $this->logger->debug('Returning from Payment Provider', $logData);
        $payment = $this->mollieGateway->getPaymentByTransactionId($transactionId, $salesChannelContext->getContext());
        $paymentStatus = $payment->getStatus();

        $paymentSettings = $this->settingsService->getPaymentSettings($salesChannelId);

        $logData['paymentStatus'] = $paymentStatus->value;
        $logData['paymentId'] = $payment->getId();

        $orderTransaction = $payment->getShopwareTransaction();
        $shopwareOrder = $orderTransaction->getOrder();
        if ($shopwareOrder instanceof OrderEntity) {
            $logData['orderNumber'] = (string) $shopwareOrder->getOrderNumber();

            if ($paymentStatus->isFailed() && ! $paymentSettings->isShopwareFailedPayment()) {
                $paymentFailedEvent = new PaymentPageFailedEvent(
                    $transactionId,
                    $shopwareOrder,
                    $payment,
                    $salesChannelContext
                );
                $this->logger->info('Payment failed, send PaymentPageFailedEvent in mollie failure mode', $logData);
                $this->eventDispatcher->dispatch($paymentFailedEvent);
            }
        }

        $query = (string) parse_url($payment->getFinalizeUrl(), PHP_URL_QUERY);
        parse_str($query, $parsedQuery);

        $queryParameters = [];
        foreach ($parsedQuery as $key => $value) {
            $queryParameters[(string) $key] = $value;
        }

        $paymentToken = $queryParameters['_sw_payment_token'] ?? null;

        if (is_string($paymentToken) && $paymentToken !== '' && $this->paymentTokenRepository->isConsumed($paymentToken)) {
            $this->logger->warning('Finalize token already consumed, skipping finalize and redirecting by payment status', $logData);

            return $this->redirectAfterFinalize($payment, $shopwareOrder);
        }

        $this->logger->info('Call shopware finalize transaction', $logData);

        // Forward to Shopware's finalize controller. We copy the inheritable routing attributes
        // and set "sw-skip-transformer" so the sub-request keeps the resolved sales channel and
        // the HttpKernel does not re-run the request transformer - which would strip the domain
        // path prefix (e.g. /de) a second time and fail to resolve the sales channel.
        $attributes = ['sw-skip-transformer' => true];
        $mainRequest = $this->container->get('request_stack')->getCurrentRequest();
        if ($mainRequest !== null) {
            $attributes = array_merge(
                $this->container->get(RequestTransformerInterface::class)->extractInheritableAttributes($mainRequest),
                $attributes
            );
        }

        $controller = sprintf('%s::%s', ShopwarePaymentController::class, 'finalizeTransaction');

        try {
            return $this->forward($controller, $attributes, $queryParameters);
        } catch (PaymentException $exception) {
            if (! $this->isInvalidTokenException($exception)) {
                throw $exception;
            }

            $logData['error'] = $exception->getMessage();
            $this->logger->warning('Finalize failed with an invalid payment token, redirecting by payment status', $logData);

            return $this->redirectAfterFinalize($payment, $shopwareOrder);
        }
    }

    #[Route(path: '/mollie/webhook/{transactionId}', name: 'frontend.mollie.webhook', methods: ['GET', 'POST'], options: ['seo' => false])]
    public function webhook(string $transactionId, Context $context): Response
    {
        try {
            $this->logger->debug('Webhook received', [
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

    private function isInvalidTokenException(PaymentException $exception): bool
    {
        return in_array($exception->getErrorCode(), [
            PaymentException::PAYMENT_TOKEN_INVALIDATED,
            PaymentException::PAYMENT_INVALID_TOKEN,
        ], true);
    }

    private function redirectAfterFinalize(Payment $payment, ?OrderEntity $shopwareOrder): Response
    {
        if (! $shopwareOrder instanceof OrderEntity) {
            return $this->redirectToRoute('frontend.account.order.page');
        }

        $orderId = $shopwareOrder->getId();
        if ($payment->getStatus()->isApproved()) {
            return $this->redirectToRoute('frontend.checkout.finish.page', ['orderId' => $orderId]);
        }

        return $this->redirectToRoute('frontend.account.edit-order.page', ['orderId' => $orderId]);
    }
}
