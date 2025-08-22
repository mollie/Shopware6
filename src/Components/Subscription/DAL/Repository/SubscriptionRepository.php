<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\DAL\Repository;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressCollection;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionHistory\SubscriptionHistoryEntity;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct\SubscriptionMetadata;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionCollection;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Components\Subscription\Exception\SubscriptionNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;

class SubscriptionRepository
{
    /** @var EntityRepository<SubscriptionCollection<SubscriptionEntity>> */
    private $repository;
    /** @var EntityRepository<SubscriptionAddressCollection<SubscriptionAddressEntity>> */
    private $addressRepository;

    /**
     * @param EntityRepository<SubscriptionCollection<SubscriptionEntity>> $repository
     * @param EntityRepository<SubscriptionAddressCollection<SubscriptionAddressEntity>> $addressRepository
     */
    public function __construct($repository, $addressRepository)
    {
        $this->repository = $repository;
        $this->addressRepository = $addressRepository;
    }

    /** @return EntityRepository<SubscriptionCollection<SubscriptionEntity>> */
    public function getRepository()
    {
        return $this->repository;
    }

    // region READ

    /**
     * @throws SubscriptionNotFoundException
     */
    public function findById(string $id, Context $context): SubscriptionEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('customer');
        $criteria->addAssociation('historyEntries');
        $criteria->addAssociation('currency');

        $result = $this->repository->search($criteria, $context);

        if ($result->count() <= 0) {
            throw new SubscriptionNotFoundException($id);
        }
        /** @var ?SubscriptionEntity $subscription */
        $subscription = $result->first();
        if ($subscription === null) {
            throw new SubscriptionNotFoundException($id);
        }

        return $subscription;
    }

    public function findByMandateId(string $customerId, string $mandateId, Context $context): SubscriptionCollection
    {
        $criteria = new Criteria();
        $criteria->addAssociation('currency');
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->addFilter(new EqualsFilter('mandateId', $mandateId));

        /** @var SubscriptionCollection<SubscriptionEntity> */
        return $this->repository->search($criteria, $context)->getEntities();
    }

    /**
     * @return EntitySearchResult<SubscriptionCollection<SubscriptionEntity>>
     */
    public function findAll(Context $context): EntitySearchResult
    {
        $criteria = new Criteria();

        return $this->repository->search($criteria, $context);
    }

    /**
     * @return EntitySearchResult<SubscriptionCollection<SubscriptionEntity>>
     */
    public function findByCustomer(string $swCustomerId, bool $includedPending, Context $context): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addAssociation('customer');
        $criteria->addAssociation('historyEntries');
        $criteria->addAssociation('currency');
        $criteria->addFilter(new EqualsFilter('customerId', $swCustomerId));

        if (! $includedPending) {
            $criteria->addFilter(
                new NotFilter(
                    MultiFilter::CONNECTION_AND,
                    [new EqualsFilter('mollieId', null)]
                )
            );
        }

        return $this->repository->search($criteria, $context);
    }

    /**
     * @throws \Exception
     *
     * @return EntitySearchResult<SubscriptionCollection<SubscriptionEntity>>
     */
    public function findByReminderRangeReached(string $salesChannelId, Context $context): EntitySearchResult
    {
        $today = (new \DateTimeImmutable());

        $criteria = new Criteria();
        $criteria->addAssociation('customer');
        $criteria->addAssociation('currency');
        // subscription is not canceled
        $criteria->addFilter(new EqualsFilter('canceledAt', null));
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));

        // payment has to be in the future
        $criteria->addFilter(new RangeFilter('nextPaymentAt', ['gte' => $today->format('Y-m-d H:i:s')]));

        return $this->repository->search($criteria, $context);
    }

    /**
     * @return EntitySearchResult<SubscriptionCollection<SubscriptionEntity>>
     */
    public function findPendingSubscriptions(string $orderId, Context $context): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addAssociation('customer');
        $criteria->addAssociation('currency');

        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addFilter(new EqualsFilter('mollieId', null));

        return $this->repository->search($criteria, $context);
    }

    // endregion

    public function insertSubscription(SubscriptionEntity $subscription, string $status, Context $context): void
    {
        $totalRoundingValue = null;
        $totalRounding = $subscription->getTotalRounding();
        if ($totalRounding instanceof CashRoundingConfig) {
            $totalRoundingValue = $totalRounding->jsonSerialize();
        }

        $itemRoundingValue = null;
        $itemRounding = $subscription->getItemRounding();
        if ($itemRounding instanceof CashRoundingConfig) {
            $itemRoundingValue = $itemRounding->jsonSerialize();
        }

        $this->repository->create(
            [
                [
                    'id' => $subscription->getId(),
                    'customerId' => $subscription->getCustomerId(),
                    // ----------------------------------------------------------
                    // new subscriptions have NULL for the external
                    // mollie data. this means the payment is not confirmed yet.
                    'mollieCustomerId' => null,
                    'mollieSubscriptionId' => null,
                    'lastRemindedAt' => null,
                    'canceledAt' => null,
                    'billingAddressId' => null,
                    'shippingAddressId' => null,
                    // ----------------------------------------------------------
                    'status' => $status,
                    'description' => $subscription->getDescription(),
                    'amount' => $subscription->getAmount(),
                    'quantity' => $subscription->getQuantity(),
                    'currencyId' => $subscription->getCurrencyId(),
                    'metadata' => $subscription->getMetadata()->toArray(),
                    'productId' => $subscription->getProductId(),
                    'orderId' => $subscription->getOrderId(),
                    'salesChannelId' => $subscription->getSalesChannelId(),
                    'totalRounding' => $totalRoundingValue,
                    'itemRounding' => $itemRoundingValue,
                ],
            ],
            $context
        );

        // now create and assign addresses if they are existing

        if ($subscription->getBillingAddress() instanceof SubscriptionAddressEntity) {
            $billing = $subscription->getBillingAddress();
            $this->assignBillingAddress($subscription->getId(), $billing, $context);
        }

        if ($subscription->getShippingAddress() instanceof SubscriptionAddressEntity) {
            $shipping = $subscription->getShippingAddress();
            $this->assignShippingAddress($subscription->getId(), $shipping, $context);
        }
    }

    public function confirmNewSubscription(string $id, string $mollieSubscriptionId, string $status, string $mollieCustomerId, string $mandateId, string $nextPaymentDate, Context $context): void
    {
        $this->repository->update(
            [
                [
                    'id' => $id,
                    'status' => $status,
                    'mollieId' => $mollieSubscriptionId,
                    'mollieCustomerId' => $mollieCustomerId,
                    'mandateId' => $mandateId,
                    'nextPaymentAt' => $nextPaymentDate,
                    'canceledAt' => null,
                ],
            ],
            $context
        );
    }

    public function updateStatus(string $id, string $status, Context $context): void
    {
        $this->repository->update(
            [
                [
                    'id' => $id,
                    'status' => $status,
                ],
            ],
            $context
        );
    }

    public function updateSubscriptionMetadata(string $id, SubscriptionMetadata $metadata, Context $context): void
    {
        $this->repository->update(
            [
                [
                    'id' => $id,
                    'metadata' => $metadata->toArray(),
                ],
            ],
            $context
        );
    }

    public function updateMandate(string $id, string $mandateId, Context $context): void
    {
        $this->repository->update(
            [
                [
                    'id' => $id,
                    'mandateId' => $mandateId,
                ],
            ],
            $context
        );
    }

    public function updateNextPaymentAt(string $id, string $nextPaymentDate, Context $context): void
    {
        $this->repository->update(
            [
                [
                    'id' => $id,
                    'nextPaymentAt' => $nextPaymentDate,
                ],
            ],
            $context
        );
    }

    public function markReminded(string $id, Context $context): void
    {
        $this->repository->update(
            [
                [
                    'id' => $id,
                    'lastRemindedAt' => new \DateTime(),
                ],
            ],
            $context
        );
    }

    public function cancelSubscription(string $id, string $status, Context $context): void
    {
        $this->repository->update(
            [
                [
                    'id' => $id,
                    'status' => $status,
                    'nextPaymentAt' => null,
                    'canceledAt' => new \DateTime(),
                ],
            ],
            $context
        );
    }

    public function skipSubscription(string $id, string $status, Context $context): void
    {
        $this->repository->update(
            [
                [
                    'id' => $id,
                    'status' => $status,
                    'nextPaymentAt' => null,
                    'canceledAt' => new \DateTime(),
                ],
            ],
            $context
        );
    }

    public function assignBillingAddress(string $subscriptionId, SubscriptionAddressEntity $address, Context $context): void
    {
        $this->upsertAddress($subscriptionId, $address, $context);

        $this->repository->update(
            [
                [
                    'id' => $subscriptionId,
                    'billingAddressId' => $address->getId(),
                ],
            ],
            $context
        );
    }

    public function assignShippingAddress(string $subscriptionId, SubscriptionAddressEntity $address, Context $context): void
    {
        $this->upsertAddress($subscriptionId, $address, $context);

        $this->repository->update(
            [
                [
                    'id' => $subscriptionId,
                    'shippingAddressId' => $address->getId(),
                ],
            ],
            $context
        );
    }

    public function addHistoryEntry(string $subscriptionId, SubscriptionHistoryEntity $historyEntity, Context $context): void
    {
        $this->repository->upsert(
            [
                [
                    'id' => $subscriptionId,
                    'historyEntries' => [
                        [
                            'id' => $historyEntity->getId(),
                            'statusFrom' => $historyEntity->getStatusFrom(),
                            'statusTo' => $historyEntity->getStatusTo(),
                            'comment' => $historyEntity->getComment(),
                            'mollieId' => $historyEntity->getMollieId(),
                            'createdAt' => $historyEntity->getCreatedAt(),
                        ],
                    ],
                ],
            ],
            $context
        );
    }

    private function upsertAddress(string $subscriptionId, SubscriptionAddressEntity $address, Context $context): void
    {
        $this->addressRepository->upsert(
            [
                [
                    'id' => $address->getId(),
                    'salutationId' => ($address->getSalutationId() === '') ? null : $address->getSalutationId(),
                    'subscriptionId' => $subscriptionId,
                    'title' => $address->getTitle(),
                    'firstName' => $address->getFirstName(),
                    'lastName' => $address->getLastName(),
                    'company' => $address->getCompany(),
                    'department' => $address->getDepartment(),
                    'vatId' => $address->getVatId(),
                    'street' => $address->getStreet(),
                    'zipcode' => $address->getZipcode(),
                    'city' => $address->getCity(),
                    'countryId' => ($address->getCountryId() === '') ? null : $address->getCountryId(),
                    'countryStateId' => ($address->getCountryStateId() === '') ? null : $address->getCountryStateId(),
                    'phoneNumber' => $address->getPhoneNumber(),
                    'additionalAddressLine1' => $address->getAdditionalAddressLine1(),
                    'additionalAddressLine2' => $address->getAdditionalAddressLine2(),
                ],
            ],
            $context
        );
    }

    // endregion
    public function updateMetadata(SubscriptionEntity $swSubscription, Context $context)
    {
        $this->repository->update(
            [
                [
                    'id' => $swSubscription->getId(),
                    'metadata' => $swSubscription->getMetadata()->toArray()
                ],
            ],
            $context
        );
    }
}
