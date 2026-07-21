<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Subscriber;

use Mollie\Shopware\Component\Payment\Event\PaymentCreatedEvent;
use Mollie\Shopware\Component\Payment\Event\PaymentInitializedEvent;
use Mollie\Shopware\Component\Payment\Event\PaymentLinkCreatedEvent;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Subscription\Action\CreateAction;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzer;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzerInterface;
use Mollie\Shopware\Component\Subscription\SubscriptionGroupAmount;
use Mollie\Shopware\Component\Subscription\SubscriptionGroupCartBuilder;
use Mollie\Shopware\Component\Subscription\SubscriptionGroupCartBuilderInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class PaymentSubscriber implements EventSubscriberInterface
{
    public const PRIORITY = 0;

    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        #[Autowire(service: LineItemAnalyzer::class)]
        private readonly LineItemAnalyzerInterface $lineItemAnalyzer,
        #[Autowire(service: SubscriptionGroupCartBuilder::class)]
        private readonly SubscriptionGroupCartBuilderInterface $groupCartBuilder,
        private readonly CreateAction $createAction,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Symfony does not walk the class hierarchy, so both concrete events are registered; the
        // handler itself only relies on the shared PaymentInitializedEvent data.
        return [
            PaymentCreatedEvent::class => ['onPaymentInitialized', self::PRIORITY],
            PaymentLinkCreatedEvent::class => ['onPaymentInitialized', self::PRIORITY],
        ];
    }

    public function onPaymentInitialized(PaymentInitializedEvent $event): void
    {
        $transactionData = $event->getTransactionDataStruct();
        $order = $transactionData->getOrder();
        $context = $event->getContext();

        if (! $this->settingsService->getSubscriptionSettings($order->getSalesChannelId())->isEnabled()) {
            return;
        }

        $existingSubscriptions = $order->getExtension('mollieSubscriptions');
        if ($existingSubscriptions instanceof SubscriptionCollection && $existingSubscriptions->count() > 0) {
            return;
        }

        $lineItems = $order->getLineItems();
        if ($lineItems === null) {
            return;
        }

        $subscriptionGroups = $this->lineItemAnalyzer->groupSubscriptionLineItemsByInterval($lineItems);
        if (count($subscriptionGroups) === 0) {
            return;
        }

        $logData = [
            'orderNumber' => (string) $order->getOrderNumber(),
            'count' => count($subscriptionGroups),
        ];
        $this->logger->info('Creating pending subscriptions for order', $logData);

        $billingAddress = $transactionData->getBillingOrderAddress();
        $shippingAddress = $transactionData->getShippingOrderAddress();
        $customer = $transactionData->getCustomer();

        foreach ($subscriptionGroups as $intervalKey => $groupLineItems) {
            /** @var OrderLineItemEntity $primaryLineItem */
            $primaryLineItem = $groupLineItems[0];

            $groupCart = $this->groupCartBuilder->buildGroupCart($order, (string) $intervalKey, $context);
            $amount = SubscriptionGroupAmount::fromGroupCartOrOrder($groupCart, $order)->gross();

            $this->createAction->create($order, $primaryLineItem, $customer, $billingAddress, $shippingAddress, $amount, $context);
        }
    }
}
