<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriptions\DTO;

use DateTimeInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Mollie\Api\Resources\Subscription;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class SubscriptionResponse
{
    /**
     * @var SalesChannelContextService
     */
    private $context;

    /**
     * @var Subscription
     */
    private $subscription;

    /**
     * @var EntityRepositoryInterface
     */
    private $customer;

    /**
     * @var DateTimeInterface|null
     */
    private $prePaymentReminderDate;

    /**
     * @param SalesChannelContextService $context
     * @param Subscription $subscription
     * @param EntityRepositoryInterface $customer
     * @param DateTimeInterface|null $prePaymentReminder
     */
    public function __construct(
        SalesChannelContextService $context,
        Subscription $subscription,
        EntityRepositoryInterface $customer,
        DateTimeInterface $prePaymentReminder = null
    ) {
        $this->subscription = $subscription;
        $this->customer = $customer;
        $this->prePaymentReminderDate = $prePaymentReminder;
        $this->context = $context;
    }

    /**
     * @return Subscription
     */
    public function getSubscription(): Subscription
    {
        return $this->subscription;
    }

    public function getId()
    {
        return $this->subscription->id;
    }

    public function getAmount()
    {
        return $this->subscription->amount->value;
    }

    public function getStatus()
    {
        return $this->subscription->status;
    }

    public function getDescription()
    {
        return $this->subscription->description;
    }

    /**
     * @return string|null
     */
    public function getParentId()
    {
        return $this->subscription->metadata && isset($this->subscription->metadata->parent_id) ?
            $this->subscription->metadata->parent_id :
            null;
    }

    public function getCreatedAt()
    {
        return $this->subscription->createdAt;
    }

    public function toArray()
    {
        $prePaymentReminderDate = null;
        if ($this->prePaymentReminderDate) {
            $prePaymentReminderDate = $this->prePaymentReminderDate->format('Y-m-d');
        }

        return [
            'id' => $this->subscription->id,
            'customer_id' => $this->subscription->customerId,
            'customer_name' => $this->getFullName(),
            'amount' => $this->subscription->amount->value,
            'mode' => $this->subscription->mode,
            'next_payment_date' => $this->subscription->nextPaymentDate,
            'status' => $this->subscription->status,
            'description' => $this->subscription->description,
            'created_at' => $this->subscription->createdAt,
            'prepayment_reminder_date' => $prePaymentReminderDate,
        ];
    }

    /**
     * @return string
     */
    private function getFullName(): string
    {
        $criteria = new Criteria();

        $mode = $this->subscription->mode;
        $mollieCustomerId = $this->customer->getCustomFields()['mollie_payments']['customer_ids'][$this->subscription->id][$mode];

        $criteria->addFilter(new EqualsFilter($this->subscription->customerId, $mollieCustomerId));

        $customerResult = $this->customer->search($criteria, $this->context)->first();


        /** @var array $name */
        $name = array_filter([
            $customerResult->getFirstName(),
            $customerResult->getLastName(),
        ]);

        return implode(' ', $name);
    }
}
