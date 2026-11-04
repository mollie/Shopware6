<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Mollie\Shopware\Component\Mollie\Session;
use Mollie\Shopware\Component\Router\RouteBuilder;
use Mollie\Shopware\Component\Router\RouteBuilderInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The payment of a session is only known once Mollie calls the session webhook, which may happen
 * before or after the shopper returns. The finish therefore only remembers the transaction of the
 * order, the session webhook attaches the payment to it. The shopper is held back until the
 * webhook set the payment status, so the right page is shown.
 */
final class PaymentFinalizer implements PaymentFinalizerInterface
{
    private const STATUS_MAX_CHECKS = 20;

    private const STATUS_CHECK_INTERVAL_MICROSECONDS = 1000000;

    private const PENDING_STATES = [
        OrderTransactionStates::STATE_OPEN,
        OrderTransactionStates::STATE_UNCONFIRMED,
    ];

    private const FAILED_STATES = [
        OrderTransactionStates::STATE_FAILED,
        OrderTransactionStates::STATE_CANCELLED,
    ];

    /**
     * @param EntityRepository<OrderTransactionCollection> $orderTransactionRepository
     */
    public function __construct(
        #[Autowire(service: SessionPaymentRepository::class)]
        private SessionPaymentRepositoryInterface $sessionPaymentRepository,
        #[Autowire(service: 'order_transaction.repository')]
        private EntityRepository $orderTransactionRepository,
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

        $context = $salesChannelContext->getContext();
        if (! $this->sessionPaymentRepository->assignTransaction($session->getId(), $transaction->getId(), $context)) {
            $this->logger->warning('Express components session has no session payment entry', $logData);
        }

        $finishUrls = $this->getFinishUrls();
        $state = $this->waitForPaymentStatus($transaction->getId(), $context);
        $logData['paymentStatus'] = $state;

        if (in_array($state, self::FAILED_STATES, true)) {
            $this->logger->info('Express components payment failed, shopper is sent to the edit order page', $logData);

            return $this->getErrorUrl($finishUrls, $orderId);
        }

        $this->logger->info('Express components checkout finished', $logData);

        return $this->getFinishUrl($finishUrls, $orderId);
    }

    /**
     * The status is set by the webhook in another request. Should it not arrive in time, the order
     * page is shown anyway and the webhook corrects the status later.
     */
    private function waitForPaymentStatus(string $transactionId, Context $context): string
    {
        $state = $this->loadPaymentStatus($transactionId, $context);
        for ($i = 1; $i < self::STATUS_MAX_CHECKS && in_array($state, self::PENDING_STATES, true); ++$i) {
            usleep(self::STATUS_CHECK_INTERVAL_MICROSECONDS);
            $state = $this->loadPaymentStatus($transactionId, $context);
        }

        return $state;
    }

    private function loadPaymentStatus(string $transactionId, Context $context): string
    {
        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation('stateMachineState');

        $transaction = $this->orderTransactionRepository->search($criteria, $context)->getEntities()->first();
        if (! $transaction instanceof OrderTransactionEntity) {
            return '';
        }

        return (string) $transaction->getStateMachineState()?->getTechnicalName();
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
