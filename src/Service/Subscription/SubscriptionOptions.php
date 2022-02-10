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
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SubscriptionOptions
{
    /**
     * @var OrderEntity
     */
    private $order;

    /**
     * @var OrderLineItemEntity
     */
    private $orderItem;

    /**
     * @var array
     */
    private $customFields;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var array
     */
    private $options = [];

    /**
     * @var string
     */
    private $transactionsId;

    /**
     * @param RouterInterface $router
     * @param TranslatorInterface $translator
     */
    public function __construct(RouterInterface $router, TranslatorInterface $translator)
    {
        $this->router = $router;
        $this->translator = $translator;
    }

    /**
     * @param OrderEntity $order
     * @param $transactionsId
     * @return SubscriptionOption[]
     * @throws Exception
     */
    public function forOrder(OrderEntity $order, $transactionsId): array
    {
        $options = [];
        $this->order = $order;
        $this->transactionsId = $transactionsId;

        foreach ($order->getLineItems() as $orderItem) {
            $payload = $orderItem->getPayload();
            $this->customFields = $payload['customFields'];

            if (!array_key_exists('mollie_subscription', $this->customFields)
                || !array_key_exists('mollie_subscription_product', $this->customFields['mollie_subscription'])
                || !$this->customFields['mollie_subscription']['mollie_subscription_product']) {
                continue;
            }

            $options[] = $this->createSubscriptionFor($orderItem);
        }

        return $options;
    }

    /**
     * @param OrderLineItemEntity $orderItem
     * @return SubscriptionOption
     */
    private function createSubscriptionFor(OrderLineItemEntity $orderItem): SubscriptionOption
    {
        $this->options = [];
        $this->orderItem = $orderItem;

        $this->addAmount();
        $this->addTimes();
        $this->addInterval();
        $this->addDescription();
        $this->addMetadata();
        $this->addWebhookUrl();
        $this->addStartDate();

        return new SubscriptionOption(
            $orderItem->getProductId(),
            $this->order->getSalesChannelId(),
            $this->options['amount'] ?? [],
            $this->options['interval'] ?? '',
            $this->options['description'] ?? '',
            $this->options['metadata'] ?? [],
            $this->options['webhookUrl'] ?? '',
            $this->options['startDate'],
            $this->options['times'] ?? null
        );
    }

    private function addAmount()
    {
        $this->options['amount'] = [
            'currency' => $this->order->getCurrency()->getIsoCode(),
            'value' => number_format($this->orderItem->getPrice()->getTotalPrice(), 2, '.', '')
        ];
    }

    private function addTimes()
    {
        $type = $this->customFields["mollie_subscription"]['mollie_subscription_repetition_amount'];
        if (!$type || $type == RepetitionType::INFINITE) {
            return;
        }

        $this->options['times'] = $this->customFields["mollie_subscription"]['mollie_subscription_repetition_amount'];
    }

    private function addInterval()
    {
        $intervalType = $this->customFields["mollie_subscription"]['mollie_subscription_interval_type'];
        $intervalAmount = (int)$this->customFields["mollie_subscription"]['mollie_subscription_interval_amount'];

        $this->options['interval'] = $intervalAmount . ' ' . $intervalType;
    }

    private function addDescription()
    {
        $this->options['description'] = $this->order->getOrderNumber() . ': '
            . $this->orderItem->getLabel() . ' - '
            . $this->getIntervalDescription();
    }

    private function addMetadata()
    {
        $payload = $this->orderItem->getPayload();
        $this->options['metadata'] = ['product_number' => $payload['productNumber']];
    }

    private function addWebhookUrl()
    {
        $webhookUrl = $this->router->generate(
            'frontend.mollie.subscriptions.webhook',
            ['transactionId' => $this->transactionsId],
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
     * @throws Exception
     */
    private function addStartDate()
    {
        $now = new \DateTimeImmutable();
        $this->options['startDate'] = $now->add(new DateInterval('P' . $this->getDateInterval()));
    }

    /**
     * Examples:
     * 7D (7 days)
     * 2W (2 weeks)
     * 3M (3 months)
     *
     * @return string
     */
    private function getDateInterval(): string
    {
        if (!isset($this->customFields["mollie_subscription"])){
            return '';
        }

        $interval = $this->customFields["mollie_subscription"]['mollie_subscription_interval_type'];
        $intervalAmount = (int)$this->customFields["mollie_subscription"]['mollie_subscription_interval_amount'];

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
    private function getIntervalDescription(): string
    {
        $intervalType = $this->customFields["mollie_subscription"]['mollie_subscription_interval_type'];
        $intervalAmount = (int)$this->customFields["mollie_subscription"]['mollie_subscription_interval_amount'];

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
