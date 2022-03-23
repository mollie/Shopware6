<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Subscription;

use DateInterval;
use Exception;
use Kiener\MolliePayments\Service\Subscription\DTO\SubscriptionOption;
use Kiener\MolliePayments\Service\WebhookBuilder\WebhookBuilder;
use Kiener\MolliePayments\Setting\Source\IntervalType;
use Kiener\MolliePayments\Setting\Source\RepetitionType;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SubscriptionOptions
{
    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @param RouterInterface $router
     * @param TranslatorInterface $translator
     */
    public function __construct(RouterInterface $router, TranslatorInterface $translator)
    {
        $this->router = $router;
        $this->translator = $translator;
    }

    public function forOrder(OrderEntity $order, string $transactionsId): array
    {
        $options = [];

        foreach ($order->getLineItems() as $orderItem) {
            $payload = $orderItem->getPayload();
            $customFields = $payload['customFields'];

            if (!array_key_exists('mollie_subscription', $customFields)
                || !array_key_exists('mollie_subscription_product', $customFields['mollie_subscription'])
                || !$customFields['mollie_subscription']['mollie_subscription_product']) {
                continue;
            }

            $options[] = $this->createSubscriptionFor($orderItem, $order, $transactionsId);
        }

        return $options;
    }

    private function createSubscriptionFor(
        OrderLineItemEntity $lineItem,
        OrderEntity $order,
        string $transactionId
    ): SubscriptionOption {
        $options = [];

        $payload = $lineItem->getPayload();
        $customFields = $payload['customFields'];

        $subscriptionId = Uuid::randomHex();

        $options = $this->addAmount($options, $order, $lineItem);
        $options = $this->addTimes($options, $customFields);
        $options = $this->addInterval($options, $customFields);
        $options = $this->addDescription($options, $order, $lineItem);
        $options = $this->addMetadata($options, $lineItem);
        $options = $this->addWebhookUrl($options, $subscriptionId);
        $options = $this->addStartDate($options, $customFields);

        return new SubscriptionOption(
            $subscriptionId,
            $lineItem->getProductId(),
            $order->getSalesChannelId(),
            $options['amount'] ?? [],
            $options['interval'] ?? '',
            $options['description'] ?? '',
            $options['metadata'] ?? [],
            $options['webhookUrl'] ?? '',
            $options['startDate'],
            $options['times'] ?? null
        );
    }

    private function addAmount(array $options, OrderEntity $order, OrderLineItemEntity $lineItem): array
    {
        $options['amount'] = [
            'currency' => $order->getCurrency()->getIsoCode(),
            'value' => number_format($lineItem->getPrice()->getTotalPrice(), 2, '.', '')
        ];

        return $options;
    }

    private function addTimes(array $options, array $customFields): array
    {
        $type = $customFields["mollie_subscription"]['mollie_subscription_repetition_type'];
        if ($type && $type !== RepetitionType::INFINITE) {
            $options['times'] = $customFields["mollie_subscription"]['mollie_subscription_repetition_amount'];
        }

        return $options;
    }

    private function addInterval(array $options, array $customFields): array
    {
        $intervalType = $customFields["mollie_subscription"]['mollie_subscription_interval_type'];
        $intervalAmount = (int)$customFields["mollie_subscription"]['mollie_subscription_interval_amount'];

        $options['interval'] = $intervalAmount . ' ' . $intervalType;

        return $options;
    }

    private function addDescription(array $options, OrderEntity $order, OrderLineItemEntity $lineItem): array
    {
        $options['description'] = $order->getOrderNumber() . ': '
            . $lineItem->getPayload()['productNumber'] . ' - '
            . $lineItem->getLabel() . ' - '
            . $this->getIntervalDescription($lineItem->getPayload()['customFields']);

        return $options;
    }

    private function addMetadata(array $options, OrderLineItemEntity $lineItem): array
    {
        $options['metadata'] = [
            'product_number' => $lineItem->getPayload()['productNumber'],
        ];

        return $options;
    }

    private function addWebhookUrl(array $options, string $subscriptionId): array
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

        $options['webhookUrl'] = $webhookUrl;

        return $options;
    }

    /**
     * @throws Exception
     */
    private function addStartDate(array $options, array $customFields): array
    {
        $now = new \DateTimeImmutable();
        $options['startDate'] = $now->add(new DateInterval('P' . $this->getDateInterval($customFields)));

        return $options;
    }

    /**
     * Examples:
     * 7D (7 days)
     * 2W (2 weeks)
     * 3M (3 months)
     *
     * @return string
     */
    private function getDateInterval(array $customFields): string
    {
        if (!isset($customFields["mollie_subscription"])){
            return '';
        }

        $interval = $customFields["mollie_subscription"]['mollie_subscription_interval_type'];
        $intervalAmount = (int)$customFields["mollie_subscription"]['mollie_subscription_interval_amount'];

        if ($interval == IntervalType::DAYS) {
            return $intervalAmount . 'D';
        }

        if ($interval == IntervalType::WEEKS) {
            return $intervalAmount . 'W';
        }

        return $intervalAmount . 'M';
    }

    /**
     * @return string
     */
    private function getIntervalDescription(array $customFields): string
    {
        $intervalType = $customFields["mollie_subscription"]['mollie_subscription_interval_type'];
        $intervalAmount = (int)$customFields["mollie_subscription"]['mollie_subscription_interval_amount'];

        if ($intervalType == IntervalType::DAYS) {
            if ($intervalAmount == 1) {
                return $this->translator->trans('mollie-subscriptions.options.everyDay');
            }
            return $this->translator->trans(
                'mollie-subscriptions.options.everyDays',
                ['%1%' => $intervalAmount]
            );
        }

        if ($intervalType == IntervalType::WEEKS) {
            if ($intervalAmount == 1) {
                return $this->translator->trans('mollie-subscriptions.options.everyWeek');
            }
            return $this->translator->trans(
                'mollie-subscriptions.options.everyWeeks',
                ['%1%' => $intervalAmount]
            );
        }

        if ($intervalType == IntervalType::MONTHS) {
            if ($intervalAmount == 1) {
                return $this->translator->trans('mollie-subscriptions.options.everyMonth');
            }
            return $this->translator->trans(
                'mollie-subscriptions.options.everyMonths',
                ['%1%' => $intervalAmount]
            );
        }

        return '';
    }
}
