<?php

namespace Kiener\MolliePayments\Components\Subscription\DAL\Repository;

use DateTime;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionHistory\SubscriptionHistoryEntity;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct\SubscriptionMetadata;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionCollection;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Components\Subscription\Exception\SubscriptionNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;

class SubscriptionRepository
{
    /**
     * @var EntityRepository
     */
    private $repoSubscriptions;

    /**
     * @var EntityRepository
     */
    private $repoAddresses;

    /**
     * @var EntityRepository
     */
    private $repoHistory;


    /**
     * @param EntityRepository $repoSubscriptions
     * @param EntityRepository $repoAddresses
     * @param EntityRepository $repoHistory
     */
    public function __construct($repoSubscriptions, $repoAddresses, $repoHistory)
    {
        $this->repoSubscriptions = $repoSubscriptions;
        $this->repoAddresses = $repoAddresses;
        $this->repoHistory = $repoHistory;
    }


    #region READ

    /**
     * @param string $id
     * @param Context $context
     * @throws SubscriptionNotFoundException
     * @return SubscriptionEntity
     */
    public function findById(string $id, Context $context): SubscriptionEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('customer');
        $criteria->addAssociation('historyEntries');

        $result = $this->repoSubscriptions->search($criteria, $context);

        if ($result->count() <= 0) {
            throw new SubscriptionNotFoundException($id);
        }

        return $result->first();
    }

    /**
     * @param string $customerId
     * @param string $mandateId
     * @param Context $context
     * @return SubscriptionCollection
     */
    public function findByMandateId(string $customerId, string $mandateId, Context $context): SubscriptionCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->addFilter(new EqualsFilter('mandateId', $mandateId));

        /** @var SubscriptionCollection $result */
        $result = $this->repoSubscriptions->search($criteria, $context)->getEntities();

        return $result;
    }

    /**
     * @param Context $context
     * @return EntitySearchResult
     */
    public function findAll(Context $context): EntitySearchResult
    {
        $criteria = new Criteria();

        return $this->repoSubscriptions->search($criteria, $context);
    }

    /**
     * @param string $swCustomerId
     * @param bool $includedPending
     * @param Context $context
     * @return EntitySearchResult
     */
    public function findByCustomer(string $swCustomerId, bool $includedPending, Context $context): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addAssociation('customer');
        $criteria->addAssociation('historyEntries');
        $criteria->addFilter(new EqualsFilter('customerId', $swCustomerId));

        if (!$includedPending) {
            $criteria->addFilter(
                new NotFilter(
                    NotFilter::CONNECTION_AND,
                    [new EqualsFilter('mollieId', null),]
                )
            );
        }

        return $this->repoSubscriptions->search($criteria, $context);
    }

    /**
     * @param string $salesChannelId
     * @param Context $context
     * @throws \Exception
     * @return EntitySearchResult
     */
    public function findByReminderRangeReached(string $salesChannelId, Context $context): EntitySearchResult
    {
        $today = (new \DateTimeImmutable);

        $criteria = new Criteria();
        $criteria->addAssociation('customer');

        # subscription is not canceled
        $criteria->addFilter(new EqualsFilter('canceledAt', null));
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));

        # payment has to be in the future
        $criteria->addFilter(new RangeFilter('nextPaymentAt', ['gte' => $today->format('Y-m-d H:i:s')]));

        return $this->repoSubscriptions->search($criteria, $context);
    }

    /**
     * @param string $orderId
     * @param Context $context
     * @return EntitySearchResult
     */
    public function findPendingSubscriptions(string $orderId, Context $context): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addAssociation('customer');

        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addFilter(new EqualsFilter('mollieId', null));

        return $this->repoSubscriptions->search($criteria, $context);
    }

    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return EntitySearchResult
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->repoSubscriptions->search($criteria, $context);
    }

    #endregion


    /**
     * @param SubscriptionEntity $subscription
     * @param string $status
     * @param Context $context
     * @return void
     */
    public function insertSubscription(SubscriptionEntity $subscription, string $status, Context $context): void
    {
        $this->repoSubscriptions->create(
            [
                [
                    'id' => $subscription->getId(),
                    'customerId' => $subscription->getCustomerId(),
                    # ----------------------------------------------------------
                    # new subscriptions have NULL for the external
                    # mollie data. this means the payment is not confirmed yet.
                    'mollieCustomerId' => null,
                    'mollieSubscriptionId' => null,
                    'lastRemindedAt' => null,
                    'canceledAt' => null,
                    'billingAddressId' => null,
                    'shippingAddressId' => null,
                    # ----------------------------------------------------------
                    'status' => $status,
                    'description' => $subscription->getDescription(),
                    'amount' => $subscription->getAmount(),
                    'quantity' => $subscription->getQuantity(),
                    'currency' => $subscription->getCurrency(),
                    'metadata' => $subscription->getMetadata()->toArray(),
                    'productId' => $subscription->getProductId(),
                    'orderId' => $subscription->getOrderId(),
                    'salesChannelId' => $subscription->getSalesChannelId(),
                ]
            ],
            $context
        );

        # now create and assign addresses if they are existing

        if ($subscription->getBillingAddress() instanceof SubscriptionAddressEntity) {
            $billing = $subscription->getBillingAddress();
            $this->assignBillingAddress($subscription->getId(), $billing, $context);
        }

        if ($subscription->getShippingAddress() instanceof SubscriptionAddressEntity) {
            $shipping = $subscription->getShippingAddress();
            $this->assignShippingAddress($subscription->getId(), $shipping, $context);
        }
    }

    /**
     * @param string $id
     * @param string $mollieSubscriptionId
     * @param string $status
     * @param string $mollieCustomerId
     * @param string $mandateId
     * @param string $nextPaymentDate
     * @param Context $context
     * @return void
     */
    public function confirmNewSubscription(string $id, string $mollieSubscriptionId, string $status, string $mollieCustomerId, string $mandateId, string $nextPaymentDate, Context $context): void
    {
        $this->repoSubscriptions->update(
            [
                [
                    'id' => $id,
                    'status' => $status,
                    'mollieId' => $mollieSubscriptionId,
                    'mollieCustomerId' => $mollieCustomerId,
                    'mandateId' => $mandateId,
                    'nextPaymentAt' => $nextPaymentDate,
                    'canceledAt' => null,
                ]
            ],
            $context
        );
    }

    /**
     * @param string $id
     * @param string $status
     * @param Context $context
     * @return void
     */
    public function updateStatus(string $id, string $status, Context $context): void
    {
        $this->repoSubscriptions->update(
            [
                [
                    'id' => $id,
                    'status' => $status,
                ]
            ],
            $context
        );
    }

    /**
     * @param string $id
     * @param SubscriptionMetadata $metadata
     * @param Context $context
     * @return void
     */
    public function updateSubscriptionMetadata(string $id, SubscriptionMetadata $metadata, Context $context): void
    {
        $this->repoSubscriptions->update(
            [
                [
                    'id' => $id,
                    'metadata' => $metadata->toArray(),
                ]
            ],
            $context
        );
    }

    /**
     * @param string $id
     * @param string $mandateId
     * @param Context $context
     * @return void
     */
    public function updateMandate(string $id, string $mandateId, Context $context): void
    {
        $this->repoSubscriptions->update(
            [
                [
                    'id' => $id,
                    'mandateId' => $mandateId,
                ]
            ],
            $context
        );
    }

    /**
     * @param string $id
     * @param string $nextPaymentDate
     * @param Context $context
     * @return void
     */
    public function updateNextPaymentAt(string $id, string $nextPaymentDate, Context $context): void
    {
        $this->repoSubscriptions->update(
            [
                [
                    'id' => $id,
                    'nextPaymentAt' => $nextPaymentDate,
                ]
            ],
            $context
        );
    }

    /**
     * @param string $id
     * @param Context $context
     */
    public function markReminded(string $id, Context $context): void
    {
        $this->repoSubscriptions->update(
            [
                [
                    'id' => $id,
                    'lastRemindedAt' => new DateTime(),
                ]
            ],
            $context
        );
    }

    /**
     * @param string $id
     * @param string $status
     * @param Context $context
     * @return void
     */
    public function cancelSubscription(string $id, string $status, Context $context): void
    {
        $this->repoSubscriptions->update(
            [
                [
                    'id' => $id,
                    'status' => $status,
                    'nextPaymentAt' => null,
                    'canceledAt' => new DateTime(),
                ]
            ],
            $context
        );
    }

    /**
     * @param string $id
     * @param string $status
     * @param Context $context
     * @return void
     */
    public function skipSubscription(string $id, string $status, Context $context): void
    {
        $this->repoSubscriptions->update(
            [
                [
                    'id' => $id,
                    'status' => $status,
                    'nextPaymentAt' => null,
                    'canceledAt' => new DateTime(),
                ]
            ],
            $context
        );
    }

    /**
     * @param string $subscriptionId
     * @param SubscriptionAddressEntity $address
     * @param Context $context
     * @return void
     */
    public function assignBillingAddress(string $subscriptionId, SubscriptionAddressEntity $address, Context $context): void
    {
        $this->upsertAddress($subscriptionId, $address, $context);

        $this->repoSubscriptions->update(
            [
                [
                    'id' => $subscriptionId,
                    'billingAddressId' => $address->getId(),
                ]
            ],
            $context
        );
    }

    /**
     * @param string $subscriptionId
     * @param SubscriptionAddressEntity $address
     * @param Context $context
     * @return void
     */
    public function assignShippingAddress(string $subscriptionId, SubscriptionAddressEntity $address, Context $context): void
    {
        $this->upsertAddress($subscriptionId, $address, $context);

        $this->repoSubscriptions->update(
            [
                [
                    'id' => $subscriptionId,
                    'shippingAddressId' => $address->getId(),
                ]
            ],
            $context
        );
    }

    /**
     * @param string $subscriptionId
     * @param SubscriptionAddressEntity $address
     * @param Context $context
     * @return void
     */
    private function upsertAddress(string $subscriptionId, SubscriptionAddressEntity $address, Context $context): void
    {
        $this->repoAddresses->upsert(
            [
                [
                    'id' => $address->getId(),
                    'subscriptionId' => $subscriptionId,
                    'salutationId' => ($address->getSalutationId() === '') ? null : $address->getSalutationId(),
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

    /**
     * @param string $subscriptionId
     * @param SubscriptionHistoryEntity $historyEntity
     * @param Context $context
     * @return void
     */
    public function addHistoryEntry(string $subscriptionId, SubscriptionHistoryEntity $historyEntity, Context $context): void
    {
        $this->repoHistory->upsert(
            [
                [
                    'id' => $historyEntity->getId(),
                    'subscriptionId' => $subscriptionId,
                    'statusFrom' => $historyEntity->getStatusFrom(),
                    'statusTo' => $historyEntity->getStatusTo(),
                    'comment' => $historyEntity->getComment(),
                    'mollieId' => $historyEntity->getMollieId(),
                    'createdAt' => $historyEntity->getCreatedAt(),
                ]
            ],
            $context
        );
    }

    #endregion
}
