<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Subscriber;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionCollection;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Payment\Event\PaymentCreatedEvent;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzer;
use Mollie\Shopware\Component\Subscription\SubscriptionMetadata;
use Mollie\Shopware\Component\Subscription\SubscriptionTag;
use Mollie\Shopware\Entity\Product\Product;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class PaymentSubscriber implements EventSubscriberInterface
{
    public const PRIORITY = 0;

    /**
     * @param EntityRepository<SubscriptionCollection<SubscriptionEntity>> $subscriptionRepository
     */
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        private readonly LineItemAnalyzer $lineItemAnalyzer,
        #[Autowire(service: 'mollie_subscription.repository')]
        private readonly EntityRepository $subscriptionRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentCreatedEvent::class => ['onPaymentCreated', self::PRIORITY],
        ];
    }

    public function onPaymentCreated(PaymentCreatedEvent $event): void
    {
        $transactionData = $event->getTransactionDataStruct();

        $order = $transactionData->getOrder();

        $salesChannelId = $order->getSalesChannelId();
        $context = $event->getContext();
        $subscriptionSettings = $this->settingsService->getSubscriptionSettings($salesChannelId);
        if (! $subscriptionSettings->isEnabled()) {
            return;
        }

        $lineItems = $order->getLineItems();
        if ($lineItems === null) {
            return;
        }
        /** @var ?OrderLineItemEntity $firstSubscriptionProduct */
        $firstSubscriptionProduct = $this->lineItemAnalyzer->getFirstSubscriptionProduct($lineItems);

        if ($firstSubscriptionProduct === null) {
            return;
        }

        $logData = [
            'orderNumber' => (string) $order->getOrderNumber(),
        ];
        $shippingAddress = $transactionData->getShippingOrderAddress();
        $billingAddress = $transactionData->getBillingOrderAddress();
        $subscriptionData = $this->getSubscriptionData($order, $firstSubscriptionProduct, $transactionData->getCustomer(),$transactionData->getCurrency());
        $subscriptionData['billingAddress'] = $this->getAddressData($billingAddress, $subscriptionData['id']);
        $subscriptionData['shippingAddress'] = $this->getAddressData($shippingAddress, $subscriptionData['id']);

        $subscriptionData['historyEntries'][] = [
            'statusFrom' => '',
            'statusTo' => SubscriptionStatus::PENDING->value,
            'comment' => 'created'
        ];

        $this->logger->info('Subscription created', $logData);
        $this->subscriptionRepository->upsert([$subscriptionData], $context);
    }

    /**
     * @return array<mixed>
     */
    private function getSubscriptionData(OrderEntity $order, OrderLineItemEntity $lineItem, CustomerEntity $customer,CurrencyEntity $currency): array
    {
        $description = $lineItem->getQuantity() . 'x ' . $lineItem->getLabel() . ' (Order #' . $order->getOrderNumber() . ', ' . $lineItem->getTotalPrice() . ' ' . $currency->getIsoCode() . ')';
        $totalRoundingValue = null;
        $totalRounding = $order->getTotalRounding();
        if ($totalRounding instanceof CashRoundingConfig) {
            $totalRoundingValue = $totalRounding->jsonSerialize();
        }
        $itemRoundingValue = null;
        $itemRounding = $order->getItemRounding();
        if ($itemRounding instanceof CashRoundingConfig) {
            $itemRoundingValue = $itemRounding->jsonSerialize();
        }

        return [
            'id' => Uuid::randomHex(),
            'customerId' => $customer->getId(),
            'mollieCustomerId' => null,
            'mollieSubscriptionId' => null,
            'lastRemindedAt' => null,
            'canceledAt' => null,
            'status' => SubscriptionStatus::PENDING->value,
            'description' => $description,
            'amount' => $order->getAmountTotal(),
            'quantity' => $lineItem->getQuantity(),
            'currencyId' => $order->getCurrencyId(),
            'metadata' => $this->getMetaDataArray($lineItem, $order->getOrderDate()),
            'productId' => (string) $lineItem->getProductId(),
            'orderId' => $order->getId(),
            'orderVersionId' => $order->getVersionId(),
            'salesChannelId' => $order->getSalesChannelId(),
            'totalRounding' => $totalRoundingValue,
            'itemRounding' => $itemRoundingValue,
            'order' => [
                'id' => $order->getId(),
                'tags' => [
                    [
                        'id' => SubscriptionTag::ID
                    ]
                ]
            ]
        ];
    }

    /**
     * @return array<mixed>
     */
    private function getAddressData(OrderAddressEntity $address, string $subscriptionId): array
    {
        return [
            'subscriptionId' => $subscriptionId,
            'salutationId' => $address->getSalutationId(),
            'firstName' => $address->getFirstName(),
            'lastName' => $address->getLastName(),
            'company' => $address->getCompany(),
            'department' => $address->getDepartment(),
            'vatId' => $address->getVatId(),
            'street' => $address->getStreet(),
            'zipcode' => (string) $address->getZipcode(),
            'city' => $address->getCity(),
            'countryId' => $address->getCountryId(),
            'countryStateId' => $address->getCountryStateId(),
            'phoneNumber' => $address->getPhoneNumber(),
            'additionalAddressLine1' => $address->getAdditionalAddressLine1(),
            'additionalAddressLine2' => $address->getAdditionalAddressLine2(),
        ];
    }

    /**
     * @return array<mixed>
     */
    private function getMetaDataArray(OrderLineItemEntity $lineItem, \DateTimeInterface $orderDate): array
    {
        /** @var ?Product $productExtension */
        $productExtension = $lineItem->getExtension(Mollie::EXTENSION);
        if (! $productExtension instanceof Product) {
            return [];
        }

        $repetition = 0;

        // Since we already paid once, we get then the next start date as first date and also reduce the amount of repetitions

        if ($productExtension->getRepetition() > 0) {
            $repetition = $productExtension->getRepetition() - 1;
        }

        $startDate = \DateTime::createFromFormat('Y-m-d', $orderDate->format('Y-m-d'));

        if (! $startDate instanceof \DateTimeInterface) {
            throw new \RuntimeException('Failed to create date object');
        }
        $startDate->modify('+' . $productExtension->getInterval() . ' ' . $productExtension->getUnit()->value);

        $metaData = new SubscriptionMetadata($startDate->format('Y-m-d'), $productExtension->getInterval(), $productExtension->getUnit(), $repetition);

        return $metaData->toArray();
    }
}
