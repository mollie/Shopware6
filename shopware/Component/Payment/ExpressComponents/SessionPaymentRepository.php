<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Mollie\Shopware\Component\Payment\ExpressComponents\DAL\SessionPaymentCollection;
use Mollie\Shopware\Component\Payment\ExpressComponents\DAL\SessionPaymentEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SessionPaymentRepository implements SessionPaymentRepositoryInterface
{
    /**
     * A new session is created with every change of the cart, most of them are never paid.
     */
    private const UNPAID_LIFETIME = '-2 hours';

    /**
     * Once the shopper is back, Mollie keeps retrying the webhook for about a day.
     */
    private const PAID_LIFETIME = '-2 days';

    private const DELETE_BATCH_SIZE = 500;

    private const SHOPPER_WAIT_SECONDS = 20;

    private const PAYMENT_ID_CHECK_INTERVAL_SECONDS = 1;

    /**
     * A webhook arriving shortly before the shopper stops waiting may be missed by the last check,
     * so the shopper counts as gone a little earlier. At worst both set the status.
     */
    private const SHOPPER_WAIT_MARGIN_SECONDS = 2;

    /**
     * @param EntityRepository<SessionPaymentCollection> $repository
     */
    public function __construct(
        #[Autowire(service: 'mollie_session_payments.repository')]
        private readonly EntityRepository $repository
    ) {
    }

    public function create(string $webhookId, string $sessionId, Context $context): void
    {
        $this->repository->create([['id' => $webhookId, 'sessionId' => $sessionId]], $context);
    }

    public function findById(string $webhookId, Context $context): ?SessionPaymentEntity
    {
        if (! Uuid::isValid($webhookId)) {
            return null;
        }

        return $this->repository->search(new Criteria([$webhookId]), $context)->getEntities()->first();
    }

    public function assignPayment(string $webhookId, string $paymentId, Context $context): void
    {
        $this->repository->update([['id' => $webhookId, 'paymentId' => $paymentId]], $context);
    }

    public function assignTransaction(string $sessionId, string $transactionId, Context $context): ?SessionPaymentEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('sessionId', $sessionId));

        $entry = $this->repository->search($criteria, $context)->getEntities()->first();
        if ($entry === null) {
            return null;
        }

        $this->repository->update([['id' => $entry->getId(), 'transactionId' => $transactionId]], $context);
        $entry->setTransactionId($transactionId);

        return $entry;
    }

    public function getFinishedPayment(SessionPaymentEntity $sessionPayment, Context $context): SessionPaymentEntity
    {
        $waited = 0;
        while ((string) $sessionPayment->getPaymentId() === '' && $waited < self::SHOPPER_WAIT_SECONDS) {
            sleep(self::PAYMENT_ID_CHECK_INTERVAL_SECONDS);
            $waited += self::PAYMENT_ID_CHECK_INTERVAL_SECONDS;
            $sessionPayment = $this->findById($sessionPayment->getId(), $context) ?? $sessionPayment;
        }

        return $sessionPayment;
    }

    public function hasShopperStoppedWaiting(string $webhookId, Context $context): bool
    {
        if (! Uuid::isValid($webhookId)) {
            return false;
        }

        $seconds = self::SHOPPER_WAIT_SECONDS - self::SHOPPER_WAIT_MARGIN_SECONDS;
        $assignedBefore = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('-' . $seconds . ' seconds')->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $criteria = new Criteria([$webhookId]);
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('transactionId', null)]));
        $criteria->addFilter(new RangeFilter('updatedAt', [RangeFilter::LTE => $assignedBefore]));

        return $this->repository->searchIds($criteria, $context)->getTotal() > 0;
    }

    public function deleteByTransactionId(string $transactionId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('transactionId', $transactionId));

        $this->deleteMatching($criteria, $context);
    }

    public function deleteExpired(\DateTimeImmutable $now, Context $context): int
    {
        // the DAL stores dates in UTC
        $now = $now->setTimezone(new \DateTimeZone('UTC'));
        $unpaidBefore = $now->modify(self::UNPAID_LIFETIME)->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $paidBefore = $now->modify(self::PAID_LIFETIME)->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $criteria = new Criteria();
        $criteria->setLimit(self::DELETE_BATCH_SIZE);
        $criteria->addFilter(new OrFilter([
            new AndFilter([
                new EqualsFilter('transactionId', null),
                new RangeFilter('createdAt', [RangeFilter::LT => $unpaidBefore]),
            ]),
            new AndFilter([
                new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('transactionId', null)]),
                new RangeFilter('createdAt', [RangeFilter::LT => $paidBefore]),
            ]),
        ]));

        return $this->deleteMatching($criteria, $context);
    }

    private function deleteMatching(Criteria $criteria, Context $context): int
    {
        /** @var array<string> $ids */
        $ids = $this->repository->searchIds($criteria, $context)->getIds();
        if ($ids === []) {
            return 0;
        }

        $this->repository->delete(array_values(array_map(static fn (string $id): array => ['id' => $id], $ids)), $context);

        return count($ids);
    }
}
