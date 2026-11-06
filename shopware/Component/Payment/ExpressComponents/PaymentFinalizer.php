<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Mollie\Shopware\Component\Mollie\Session;
use Mollie\Shopware\Component\Payment\ExpressComponents\Route\AbstractSessionWebhookRoute;
use Mollie\Shopware\Component\Payment\ExpressComponents\Route\SessionWebhookRoute;
use Mollie\Shopware\Component\Router\RouteBuilder;
use Mollie\Shopware\Component\Router\RouteBuilderInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\Token\JWTFactoryV2;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterfaceV2;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The payment of a session is only known once Mollie calls the session webhook, which may happen
 * before or after the shopper returns. The shopper is held back until the payment id is there,
 * then sent through the Shopware finalize, which decides between finish and error page.
 */
final class PaymentFinalizer implements PaymentFinalizerInterface
{
    public function __construct(
        #[Autowire(service: SessionPaymentRepository::class)]
        private SessionPaymentRepositoryInterface $sessionPaymentRepository,
        #[Autowire(service: SessionWebhookRoute::class)]
        private AbstractSessionWebhookRoute $sessionWebhookRoute,
        /** @phpstan-ignore classConstant.deprecatedClass, parameter.deprecatedInterface */
        #[Autowire(service: JWTFactoryV2::class)]
        private TokenFactoryInterfaceV2 $tokenFactory,
        #[Autowire(service: RouteBuilder::class)]
        private RouteBuilderInterface $routeBuilder,
        private RequestStack $requestStack,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public function finalize(Session $session, OrderEntity $order, SalesChannelContext $salesChannelContext): string
    {
        $orderId = $order->getId();

        $transaction = $this->getLatestTransaction($order);
        if (! $transaction instanceof OrderTransactionEntity) {
            throw ExpressComponentsException::orderTransactionMissing($orderId);
        }

        $logData = [
            'orderId' => $orderId,
            'orderNumber' => $order->getOrderNumber(),
            'sessionId' => $session->getId(),
            'transactionId' => $transaction->getId(),
        ];

        $finishUrls = $this->getFinishUrls();
        $context = $salesChannelContext->getContext();
        $sessionPayment = $this->sessionPaymentRepository->assignTransaction($session->getId(), $transaction->getId(), $context);
        if ($sessionPayment === null) {
            $this->logger->warning('Express components session has no session payment entry', $logData);

            return $this->getFinishUrl($finishUrls, $orderId);
        }

        $sessionPayment = $this->sessionPaymentRepository->getFinishedPayment($sessionPayment, $context);
        $paymentId = (string) $sessionPayment->getPaymentId();
        if ($paymentId === '') {
            $this->logger->info('Express components webhook did not arrive in time, the webhook sets the status later', $logData);

            return $this->getFinishUrl($finishUrls, $orderId);
        }
        $logData['paymentId'] = $paymentId;

        // the webhook may still be attaching the payment in its own request, so it is attached here
        // as well before the finalize reads it from the transaction
        $webhookRequest = new Request([], ['id' => $paymentId]);
        try {
            $this->sessionWebhookRoute->notify($sessionPayment->getId(), $webhookRequest, $context);
        } catch (\Throwable $exception) {
            $logData['error'] = $exception->getMessage();
            $this->logger->error('Express components payment could not be attached during the finish', $logData);

            return $this->getFinishUrl($finishUrls, $orderId);
        }

        $this->logger->info('Express components checkout continues with the Shopware finalize', $logData);

        return $this->getFinalizeUrl($transaction, $finishUrls, $orderId);
    }

    private function getFinalizeUrl(OrderTransactionEntity $transaction, FinishUrls $finishUrls, string $orderId): string
    {
        /** @phpstan-ignore method.deprecatedClass, new.deprecatedClass */
        $tokenStruct = new TokenStruct(
            null,
            null,
            $transaction->getPaymentMethodId(),
            $transaction->getId(),
            $this->getFinishUrl($finishUrls, $orderId),
            null,
            $this->getErrorUrl($finishUrls, $orderId),
        );

        /** @phpstan-ignore method.deprecatedInterface */
        $token = $this->tokenFactory->generateToken($tokenStruct);

        return $this->routeBuilder->getFinalizeUrl($token);
    }

    /**
     * The newest transaction is the one of the current attempt. Sorting by creation date works on
     * every supported Shopware version, unlike primaryOrderTransactionId.
     */
    private function getLatestTransaction(OrderEntity $order): ?OrderTransactionEntity
    {
        $transactions = $order->getTransactions();
        if ($transactions === null) {
            return null;
        }

        $sorted = $transactions->getElements();
        uasort($sorted, static function (OrderTransactionEntity $left, OrderTransactionEntity $right): int {
            return ($right->getCreatedAt()?->getTimestamp() ?? 0) <=> ($left->getCreatedAt()?->getTimestamp() ?? 0);
        });

        $latest = reset($sorted);

        return $latest instanceof OrderTransactionEntity ? $latest : null;
    }

    private function getFinishUrl(FinishUrls $finishUrls, string $orderId): string
    {
        $finishUrl = $finishUrls->getFinishUrl($orderId);
        if ($finishUrl === '') {
            return $this->routeBuilder->getCheckoutFinishUrl($orderId);
        }

        return $finishUrl;
    }

    private function getErrorUrl(FinishUrls $finishUrls, string $orderId): string
    {
        $errorUrl = $finishUrls->getErrorUrl($orderId);
        if ($errorUrl === '') {
            return $this->routeBuilder->getEditOrderUrl($orderId);
        }

        return $errorUrl;
    }

    /**
     * The finish and error url are parameters of the request Mollie sent the shopper back with, so
     * they are read from that request instead of being handed down through every flow.
     */
    private function getFinishUrls(): FinishUrls
    {
        $request = $this->requestStack->getMainRequest();
        if (! $request instanceof Request) {
            return new FinishUrls('', '');
        }

        return FinishUrls::fromRequest($request);
    }
}
