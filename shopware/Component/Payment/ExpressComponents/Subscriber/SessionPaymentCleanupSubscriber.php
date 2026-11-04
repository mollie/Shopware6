<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents\Subscriber;

use Mollie\Shopware\Component\Payment\Event\PaymentFinalizeEvent;
use Mollie\Shopware\Component\Payment\ExpressComponents\SessionBuilder;
use Mollie\Shopware\Component\Payment\ExpressComponents\SessionPaymentRepository;
use Mollie\Shopware\Component\Payment\ExpressComponents\SessionPaymentRepositoryInterface;
use Mollie\Shopware\Component\Transaction\TransactionService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Once an express components payment is finalized, the transaction is an ordinary Shopware
 * payment, so the traces of the session are removed from order and transaction.
 */
final class SessionPaymentCleanupSubscriber implements EventSubscriberInterface
{
    // below DevWebHookSubscriber, which still reads the express checkout marker on this event
    private const PRIORITY = -100;

    /**
     * @param EntityRepository<OrderTransactionCollection> $orderTransactionRepository
     */
    public function __construct(
        #[Autowire(service: 'order_transaction.repository')]
        private EntityRepository $orderTransactionRepository,
        #[Autowire(service: SessionPaymentRepository::class)]
        private SessionPaymentRepositoryInterface $sessionPaymentRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentFinalizeEvent::class => ['onFinalize', self::PRIORITY],
        ];
    }

    public function onFinalize(PaymentFinalizeEvent $event): void
    {
        $transaction = $event->getPayment()->getShopwareTransaction();
        $customFields = $transaction->getCustomFields() ?? [];
        if (($customFields[TransactionService::TRANSACTION_CUSTOM_FIELD_EXPRESS_CHECKOUT] ?? false) !== true) {
            return;
        }

        $this->orderTransactionRepository->upsert([
            [
                'id' => $transaction->getId(),
                'customFields' => [TransactionService::TRANSACTION_CUSTOM_FIELD_EXPRESS_CHECKOUT => null],
                'order' => [
                    'id' => $transaction->getOrderId(),
                    'customFields' => [SessionBuilder::ORDER_CUSTOM_FIELD => null],
                ],
            ],
        ], $event->getContext());

        $this->sessionPaymentRepository->deleteByTransactionId($transaction->getId(), $event->getContext());

        $this->logger->info('Express components session removed after finalize', [
            'transactionId' => $transaction->getId(),
            'orderId' => $transaction->getOrderId(),
        ]);
    }
}
