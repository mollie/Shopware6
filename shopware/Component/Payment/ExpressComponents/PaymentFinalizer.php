<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Mollie\Shopware\Component\Mollie\Session;
use Mollie\Shopware\Component\Router\RouteBuilder;
use Mollie\Shopware\Component\Router\RouteBuilderInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The payment of a session is only known once Mollie calls the session webhook, which may happen
 * before or after the shopper returns. The finish therefore only remembers the transaction of the
 * order, the session webhook attaches the payment to it.
 */
final class PaymentFinalizer implements PaymentFinalizerInterface
{
    public function __construct(
        #[Autowire(service: SessionPaymentRepository::class)]
        private SessionPaymentRepositoryInterface $sessionPaymentRepository,
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

        if (! $this->sessionPaymentRepository->assignTransaction($session->getId(), $transaction->getId())) {
            $this->logger->warning('Express components session has no session payment entry', $logData);
        }

        return $this->getFinishUrl($this->getFinishUrls(), $orderId);
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

    /**
     * The finish url is a parameter of the request Mollie sent the shopper back with, so it is read
     * from that request instead of being handed down through every flow.
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
