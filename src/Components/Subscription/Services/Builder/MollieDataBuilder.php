<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\Services\Builder;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Service\WebhookBuilder\WebhookBuilder;

class MollieDataBuilder
{

    /**
     * @var WebhookBuilder
     */
    private $webhookBuilder;


    /**
     * @param WebhookBuilder $webhookBuilder
     */
    public function __construct(WebhookBuilder $webhookBuilder)
    {
        $this->webhookBuilder = $webhookBuilder;
    }


    /**
     * @param SubscriptionEntity $subscription
     * @param string $mandateId
     * @return array<mixed>
     */
    public function buildRequestPayload(SubscriptionEntity $subscription, string $mandateId): array
    {
        $metadata = $subscription->getMetadata();

        $startDate = $metadata->getStartDate();
        $interval = $metadata->getInterval() . ' ' . $metadata->getIntervalUnit();
        $times = ($metadata->getTimes() > 0) ? $metadata->getTimes() : null;

        return [
            'amount' => [
                'currency' => $subscription->getCurrency(),
                'value' => number_format($subscription->getAmount(), 2, '.', '')
            ],
            'description' => $subscription->getDescription(),
            'metadata' => [],
            'webhookUrl' => $this->webhookBuilder->buildSubscriptionWebhook($subscription->getId()),
            'startDate' => $startDate,
            'interval' => $interval,
            'times' => $times,
            'mandateId' => $mandateId,
        ];
    }
}
