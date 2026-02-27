<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\Services\Builder;

use Kiener\MolliePayments\Service\Router\RoutingBuilder;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;

class MollieDataBuilder
{
    /**
     * @var RoutingBuilder
     */
    private $routingBuilder;

    public function __construct(RoutingBuilder $routingBuilder)
    {
        $this->routingBuilder = $routingBuilder;
    }

    /**
     * @param int $times ,
     *
     * @return array<mixed>
     */
    public function buildRequestPayload(SubscriptionEntity $subscription, string $startDate, string $interval, string $intervalUnit, int $times, string $mandateId): array
    {
        $intervalValue = $interval . ' ' . $intervalUnit;
        $timesValue = ($times > 0) ? $times : null;
        $currency = $subscription->getCurrency();
        $currencyIso = '';

        if ($currency !== null) {
            $currencyIso = $currency->getIsoCode();
        }

        return [
            'amount' => [
                'currency' => $currencyIso,
                'value' => number_format($subscription->getAmount(), 2, '.', ''),
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
