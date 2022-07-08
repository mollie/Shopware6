<?php

namespace Kiener\MolliePayments\Components\Subscription\DAL\Repository;


use DateTime;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct\SubscriptionMetadata;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;

class SubscriptionRepository
{

    /**
     * @var EntityRepositoryInterface
     */
    private $repoSubscriptions;

    /**
     * @var EntityRepositoryInterface
     */
    private $repoAddresses;


    /**
     * @param EntityRepositoryInterface $repoSubscriptions
     * @param EntityRepositoryInterface $repoAddresses
     */
    public function __construct(EntityRepositoryInterface $repoSubscriptions, EntityRepositoryInterface $repoAddresses)
    {
        $this->repoSubscriptions = $repoSubscriptions;
        $this->repoAddresses = $repoAddresses;
    }

#region READ

    /**
     * @return EntityRepositoryInterface
     */
    public function getRepository(): EntityRepositoryInterface
    {
        return $this->repoSubscriptions;
    }

    /**
     * @param string $id
     * @param Context $context
     * @return SubscriptionEntity
     */
    public function findById(string $id, Context $context): SubscriptionEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('customer');

        return $this->repoSubscriptions->search($criteria, $context)->first();
    }

    /**
     * @param Context $context
     * @return EntitySearchResult<SubscriptionEntity>
     */
    public function findAll(Context $context): EntitySearchResult
    {
        $criteria = new Criteria();

        return $this->repoSubscriptions->search($criteria, $context);
    }

    /**
     * @param int $daysOffset
     * @param string $salesChannelId
     * @param Context $context
     * @return EntitySearchResult<SubscriptionEntity>
     * @throws \Exception
     */
    public function findByReminderRangeReached(int $daysOffset, string $salesChannelId, Context $context): EntitySearchResult
    {
        # let's use our current date and remove the
        # provided number of offset days.
        # the final result is the days in advance
        $interval = new \DateInterval('P' . $daysOffset . 'D');
        $prepaymentDate = (new \DateTimeImmutable)->sub($interval);

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
     * @return EntitySearchResult<SubscriptionEntity>
     */
    public function findPendingSubscriptions(string $orderId, Context $context): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addAssociation('customer');

        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addFilter(new EqualsFilter('mollieId', null));

        return $this->repoSubscriptions->search($criteria, $context);
    }

#endregion

#region INSERT/UPDATE

    /**
     * @param SubscriptionEntity $subscription
     * @param Context $context
     */
    public function insertSubscription(SubscriptionEntity $subscription, Context $context): void
    {
        $this->repoSubscriptions->create([
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
     * @param string $mollieCustomerId
     * @param string $nextPaymentDate
     * @param Context $context
     */
    public function confirmSubscription(string $id, string $mollieSubscriptionId, string $mollieCustomerId, string $nextPaymentDate, Context $context): void
    {
        $this->repoSubscriptions->update([
            [
                'id' => $id,
                'mollieId' => $mollieSubscriptionId,
                'mollieCustomerId' => $mollieCustomerId,
                'nextPaymentAt' => $nextPaymentDate,
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
        $this->repoSubscriptions->update([
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
     * @param string $nextPaymentDate
     * @param Context $context
     * @return void
     */
    public function updateNextPaymentAt(string $id, string $nextPaymentDate, Context $context): void
    {
        $this->repoSubscriptions->update([
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
        $this->repoSubscriptions->update([
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
     * @param Context $context
     */
    public function cancelSubscription(string $id, Context $context): void
    {
        $this->repoSubscriptions->update([
            [
                'id' => $id,
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

        $this->repoSubscriptions->update([
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

        $this->repoSubscriptions->update([
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

#endregion

}
