<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Mollie\Shopware\Component\Mollie\Gateway\SessionGateway;
use Mollie\Shopware\Component\Mollie\Gateway\SessionGatewayInterface;
use Mollie\Shopware\Component\Mollie\Mode;
use Mollie\Shopware\Component\Payment\Route\AbstractWebhookRoute;
use Mollie\Shopware\Component\Payment\Route\WebhookRoute;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Finishes express components orders whose Mollie session had no payment id yet when the shopper
 * came back. Mollie adds the payment to a completed session only a little later, so the order was
 * created without a payment. Every run looks the session up again; the number of lookups is kept
 * on the order and an order that never gets a payment id is given up after MAX_CHECKS.
 */
final class PendingSessionPaymentAction
{
    public const MAX_CHECKS = 5;
    public const CHECKS_KEY = 'checks';

    private const BATCH_SIZE = 10;

    // the finish request itself may still be attaching the payment to a fresh order
    private const MIN_ORDER_AGE = '-2 minutes';

    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        #[Autowire(service: 'order.repository')]
        private readonly EntityRepository $orderRepository,
        #[Autowire(service: 'sales_channel.repository')]
        private readonly EntityRepository $salesChannelRepository,
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        #[Autowire(service: SessionGateway::class)]
        private readonly SessionGatewayInterface $sessionGateway,
        #[Autowire(service: PaymentFinalizer::class)]
        private readonly PaymentFinalizerInterface $paymentFinalizer,
        #[Autowire(service: WebhookRoute::class)]
        private readonly AbstractWebhookRoute $webhookRoute,
        #[Autowire(service: SalesChannelContextService::class)]
        private readonly SalesChannelContextServiceInterface $salesChannelContextService,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return int number of orders that received their payment
     */
    public function execute(): int
    {
        $context = new Context(new SystemSource());
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        /** @var array<string> $salesChannelIds */
        $salesChannelIds = $this->salesChannelRepository->searchIds($criteria, $context)->getIds();

        $finished = 0;
        foreach ($salesChannelIds as $salesChannelId) {
            if (! $this->settingsService->getExpressComponentsSettings($salesChannelId)->isEnabled()) {
                continue;
            }

            $finished += $this->finishSalesChannel($salesChannelId, $context);
        }

        return $finished;
    }

    private function finishSalesChannel(string $salesChannelId, Context $context): int
    {
        $mode = $this->settingsService->getApiSettings($salesChannelId)->getMode();
        $orders = $this->findPendingOrders($salesChannelId, $mode, $context);
        if ($orders->count() === 0) {
            return 0;
        }

        $this->countCheck($orders, $mode, $context);

        $finished = 0;
        foreach ($orders as $order) {
            if ($this->finishOrder($order, $mode, $salesChannelId)) {
                ++$finished;
            }
        }

        return $finished;
    }

    private function findPendingOrders(string $salesChannelId, Mode $mode, Context $context): OrderCollection
    {
        $sessionPath = sprintf('customFields.%s.%s.%s', SessionBuilder::ORDER_CUSTOM_FIELD, $mode->value, SessionBuilder::ORDER_CUSTOM_FIELD_SESSION_ID);
        $checksPath = sprintf('customFields.%s.%s.%s', SessionBuilder::ORDER_CUSTOM_FIELD, $mode->value, self::CHECKS_KEY);
        $paymentPath = sprintf('customFields.%s.payment_id', Mollie::EXTENSION);
        $latest = new \DateTimeImmutable(self::MIN_ORDER_AGE);

        $criteria = new Criteria();
        $criteria->addAssociation('transactions');
        $criteria->addAssociation('orderCustomer');
        $criteria->getAssociation('transactions')->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter($sessionPath, null)]));
        $criteria->addFilter(new EqualsFilter($paymentPath, null));
        $criteria->addFilter(new OrFilter([
            new EqualsFilter($checksPath, null),
            new RangeFilter($checksPath, [RangeFilter::LT => self::MAX_CHECKS]),
        ]));
        $criteria->addFilter(new RangeFilter('orderDateTime', [RangeFilter::LTE => $latest->format(Defaults::STORAGE_DATE_TIME_FORMAT)]));
        $criteria->addSorting(new FieldSorting('orderDateTime', FieldSorting::ASCENDING));
        $criteria->setLimit(self::BATCH_SIZE);

        return $this->orderRepository->search($criteria, $context)->getEntities();
    }

    /**
     * Counted before the lookup, so an order whose lookup keeps failing is given up as well.
     */
    private function countCheck(OrderCollection $orders, Mode $mode, Context $context): void
    {
        $updates = [];
        foreach ($orders as $order) {
            $customFields = $order->getCustomFields() ?? [];
            $checks = (int) ($customFields[SessionBuilder::ORDER_CUSTOM_FIELD][$mode->value][self::CHECKS_KEY] ?? 0);
            $customFields[SessionBuilder::ORDER_CUSTOM_FIELD][$mode->value][self::CHECKS_KEY] = $checks + 1;
            $order->setCustomFields($customFields);

            $updates[] = ['id' => $order->getId(), 'customFields' => $customFields];
        }

        $this->orderRepository->update($updates, $context);
    }

    private function finishOrder(OrderEntity $order, Mode $mode, string $salesChannelId): bool
    {
        $customFields = $order->getCustomFields() ?? [];
        $sessionId = SessionBuilder::readOrderSessionId($customFields, $mode) ?? '';
        $checks = (int) ($customFields[SessionBuilder::ORDER_CUSTOM_FIELD][$mode->value][self::CHECKS_KEY] ?? 0);
        $logData = [
            'orderId' => $order->getId(),
            'orderNumber' => $order->getOrderNumber(),
            'sessionId' => $sessionId,
            'checks' => $checks,
            'salesChannelId' => $salesChannelId,
        ];

        try {
            $salesChannelContext = $this->buildContext($order, $salesChannelId);
            $session = $this->sessionGateway->getSession($sessionId, $salesChannelContext);
            if ($session->getPaymentId() === '') {
                $this->logPending($checks, $logData);

                return false;
            }

            $this->paymentFinalizer->finalize($session, $order, $salesChannelContext);
            $transaction = $order->getTransactions()?->first();
            if (! $transaction instanceof OrderTransactionEntity) {
                return false;
            }

            $this->webhookRoute->notify($transaction->getId(), $salesChannelContext->getContext());
            $this->logger->info('Express components payment added to the order afterwards', $logData);

            return true;
        } catch (\Throwable $exception) {
            $logData['error'] = $exception->getMessage();
            $this->logger->error('Failed to add the express components payment to the order', $logData);

            return false;
        }
    }

    /**
     * @param array<string, mixed> $logData
     */
    private function logPending(int $checks, array $logData): void
    {
        if ($checks < self::MAX_CHECKS) {
            $this->logger->debug('Express components session has no payment id yet', $logData);

            return;
        }

        $this->logger->error('Express components session never received a payment id, order is given up', $logData);
    }

    private function buildContext(OrderEntity $order, string $salesChannelId): SalesChannelContext
    {
        $parameters = new SalesChannelContextServiceParameters(
            $salesChannelId,
            Uuid::randomHex(),
            customerId: $order->getOrderCustomer()?->getCustomerId(),
        );

        return $this->salesChannelContextService->get($parameters);
    }
}
