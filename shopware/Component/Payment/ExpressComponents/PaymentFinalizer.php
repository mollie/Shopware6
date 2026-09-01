<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Session;
use Mollie\Shopware\Component\Router\RouteBuilder;
use Mollie\Shopware\Component\Router\RouteBuilderInterface;
use Mollie\Shopware\Component\Transaction\TransactionService;
use Mollie\Shopware\Component\Transaction\TransactionServiceInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractHandlePaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\HandlePaymentMethodRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Brings the payment that already exists on Mollie's side together with the Shopware order,
 * no matter whether that order was just created from a cart or existed before.
 */
final class PaymentFinalizer implements PaymentFinalizerInterface
{
    public function __construct(
        #[Autowire(service: MollieGateway::class)]
        private MollieGatewayInterface $mollieGateway,
        #[Autowire(service: TransactionService::class)]
        private TransactionServiceInterface $transactionService,
        #[Autowire(service: HandlePaymentMethodRoute::class)]
        private AbstractHandlePaymentMethodRoute $handlePaymentMethodRoute,
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

        $this->attachPayment($session, $order, $transaction, $salesChannelContext, $logData);

        return $this->handlePayment($orderId, $salesChannelContext, $logData);
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

    /**
     * The payment already exists on Mollie's side, it is loaded once and written onto the
     * order transaction the same way a regular payment would be, so webhooks, refunds and the
     * ERP exports find it under the usual keys.
     *
     * @param array<mixed> $logData
     */
    private function attachPayment(Session $session, OrderEntity $order, OrderTransactionEntity $transaction, SalesChannelContext $salesChannelContext, array $logData): void
    {
        $paymentId = $session->getPaymentId();
        if ($paymentId === '') {
            $this->logger->error('Express components session carries no payment id', $logData);

            return;
        }

        $payment = $this->mollieGateway->getPayment($paymentId, (string) $order->getOrderNumber(), $salesChannelContext->getSalesChannelId());

        $this->transactionService->savePaymentExtension($transaction->getId(), $order, $payment, $salesChannelContext->getContext());
    }

    /**
     * Runs the regular Shopware payment handling on the order, which is what patches the
     * Mollie payment with everything only Shopware knows: the description built from the order
     * number, the webhook url and the metadata. Because the payment id is already on the
     * transaction, the Pay action updates that payment instead of creating one.
     *
     * A failure here must not abort the checkout: the shopper already paid and the order exists.
     *
     * @param array<mixed> $logData
     */
    private function handlePayment(string $orderId, SalesChannelContext $salesChannelContext, array $logData): string
    {
        $finishUrls = $this->getFinishUrls();

        $finishUrl = $finishUrls->getFinishUrl($orderId);
        if ($finishUrl === '') {
            $finishUrl = $this->routeBuilder->getCheckoutFinishUrl($orderId);
        }

        $errorUrl = $finishUrls->getErrorUrl($orderId);
        if ($errorUrl === '') {
            $errorUrl = $this->routeBuilder->getEditOrderUrl($orderId);
        }

        $paymentRequest = new Request();
        $paymentRequest->request->set('orderId', $orderId);
        $paymentRequest->request->set(FinishUrls::FINISH_URL_PARAMETER, $finishUrl);
        $paymentRequest->request->set(FinishUrls::ERROR_URL_PARAMETER, $errorUrl);

        try {
            $handlePaymentResponse = $this->handlePaymentMethodRoute->load($paymentRequest, $salesChannelContext);
            $this->logger->debug('Express components payment handled', $logData);

            $redirectResponse = $handlePaymentResponse->getRedirectResponse();
            if ($redirectResponse instanceof RedirectResponse) {
                return $redirectResponse->getTargetUrl();
            }
        } catch (\Throwable $exception) {
            $logData['error'] = $exception->getMessage();
            $this->logger->error('Failed to handle the express components payment', $logData);
        }

        return $finishUrl;
    }

    /**
     * The finish and error url are parameters of the request Mollie sent the shopper back with,
     * so they are read from that request instead of being handed down through every flow. Without
     * a request - a queue worker or the CLI - there is nothing to read and the storefront pages
     * are used.
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
