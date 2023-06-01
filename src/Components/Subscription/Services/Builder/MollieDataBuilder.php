<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\Services\Builder;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Service\Router\RoutingBuilder;

class MollieDataBuilder
{
    /**
     * @var RoutingBuilder
     */
    private $routingBuilder;


    /**
     * @param RoutingBuilder $routingBuilder
     */
    public function __construct(RoutingBuilder $routingBuilder)
    {
        $this->routingBuilder = $routingBuilder;
    }


    /**
     * @param SubscriptionEntity $subscription
     * @param string $startDate
     * @param string $interval
     * @param string $intervalUnit
     * @param int $times ,
     * @param string $mandateId
     * @return array<mixed>
     */
    public function buildRequestPayload(SubscriptionEntity $subscription, string $startDate, string $interval, string $intervalUnit, int $times, string $mandateId): array
    {
        $intervalValue = $interval . ' ' . $intervalUnit;
        $timesValue = ($times > 0) ? $times : null;

        return [
            'amount' => [
                'currency' => $subscription->getCurrency(),
                'value' => number_format($subscription->getAmount(), 2, '.', '')
            ],
            'description' => $subscription->getDescription(),
            'metadata' => [],
            'webhookUrl' => $this->routingBuilder->buildSubscriptionWebhook($subscription->getId()),
            'startDate' => $startDate,
            'interval' => $intervalValue,
            'times' => $timesValue,
            'mandateId' => $mandateId,
        ];
    }
}
