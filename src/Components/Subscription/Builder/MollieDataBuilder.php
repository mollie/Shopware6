<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\Builder;

use DateInterval;
use Exception;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Components\Subscription\DTO\SubscriptionOption;
use Kiener\MolliePayments\Gateway\Mollie\Model\SubscriptionDefinition;
use Kiener\MolliePayments\Gateway\Mollie\Model\SubscriptionDefinitionInterface;
use Kiener\MolliePayments\Service\WebhookBuilder\WebhookBuilder;
use Kiener\MolliePayments\Setting\Source\IntervalType;
use Kiener\MolliePayments\Setting\Source\RepetitionType;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MollieDataBuilder
{

    /**
     * @var RouterInterface
     */
    private $router;


    /**
     * @param RouterInterface $router
     */
    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * @param SubscriptionEntity $subscription
     * @return array<mixed>
     */
    public function buildDefinition(SubscriptionEntity $subscription): array
    {
        $now = new \DateTimeImmutable();
        #  $startDate = $now->add(new DateInterval('P' . $this->getDateInterval($subscription->getIntervalValue(), $subscription->getIntervalType())));

        $metadata = [
            #     'product_number' => $lineItem->getPayload()['productNumber'],
        ];

        $interval = $subscription->getIntervalValue() . ' ' . $subscription->getIntervalType();

        $data = [
            'amount' => [
                'currency' => $subscription->getCurrencyIso(),
                'value' => number_format($subscription->getAmount(), 2, '.', '')
            ],
            'interval' => $interval,
            'description' => $subscription->getDescription(),
            'metadata' => $metadata,
            'webhookUrl' => $this->getWebhook($subscription->getId()),
            'startDate' => $subscription->getStartDate(),
        ];

        if ($subscription->getRepetitionAmount() !== '') {
            $data['times'] = $subscription->getRepetitionAmount();
        }

        return $data;
    }


    private function getWebhook(string $subscriptionId): string
    {
        $webhookUrl = $this->router->generate(
            'frontend.mollie.subscriptions.webhook',
            ['subscriptionId' => $subscriptionId],
            $this->router::ABSOLUTE_URL
        );

        $customDomain = trim((string)getenv(WebhookBuilder::CUSTOM_DOMAIN_ENV_KEY));

        if ($customDomain !== '') {

            $components = parse_url($webhookUrl);

            # replace old domain with new custom domain
            $webhookUrl = str_replace((string)$components['host'], $customDomain, $webhookUrl);
        }

        return $webhookUrl;
    }


    /**
     * Examples:
     * 7D (7 days)
     * 2W (2 weeks)
     * 3M (3 months)
     *
     * @return string
     */
    private function getDateInterval(string $intervalValue, string $intervalType): string
    {
        if (!isset($customFields["mollie_subscription"])) {
            return '';
        }


        if ($intervalType == IntervalType::DAYS) {
            return $intervalValue . 'D';
        }

        if ($intervalType == IntervalType::WEEKS) {
            return $intervalValue . 'W';
        }

        return $intervalValue . 'M';
    }


}
