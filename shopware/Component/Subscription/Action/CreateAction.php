<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Action;

use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\SubscriptionMetadata;
use Mollie\Shopware\Component\Subscription\SubscriptionTag;
use Mollie\Shopware\Entity\Product\Product;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CreateAction
{
    /**
     * @param EntityRepository<SubscriptionCollection<SubscriptionEntity>> $subscriptionRepository
     */
    public function __construct(
        #[Autowire(service: 'mollie_subscription.repository')]
        private readonly EntityRepository $subscriptionRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    public function create(
        OrderEntity $order,
        OrderLineItemEntity $primaryLineItem,
        CustomerEntity $customer,
        OrderAddressEntity $billingAddress,
        OrderAddressEntity $shippingAddress,
        float $amount,
        Context $context
    ): string {
        $subscriptionId = Uuid::randomHex();

        $logData = [
            'subscriptionId' => $subscriptionId,
            'orderNumber' => (string) $order->getOrderNumber(),
        ];
        $this->logger->info('Persisting pending subscription for order', $logData);

        $subscriptionData = $this->buildSubscriptionData($subscriptionId, $order, $primaryLineItem, $customer, $amount);
        $subscriptionData['billingAddress'] = $this->buildAddressData($billingAddress, $subscriptionId);
        $subscriptionData['shippingAddress'] = $this->buildAddressData($shippingAddress, $subscriptionId);

        $this->subscriptionRepository->upsert([$subscriptionData], $context);

        return $subscriptionId;
    }

    /**
     * @return array<mixed>
     */
    private function buildSubscriptionData(string $subscriptionId, OrderEntity $order, OrderLineItemEntity $primaryLineItem, CustomerEntity $customer, float $amount): array
    {
        $description = 'Order #' . $order->getOrderNumber();

        /** @var ?Product $productExtension */
        $productExtension = $primaryLineItem->getExtension(Mollie::EXTENSION);
        if ($productExtension instanceof Product) {
            $description .= ' (' . $productExtension->getInterval() . ')';
        }

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
            'id' => $subscriptionId,
            'customerId' => $customer->getId(),
            'mollieCustomerId' => null,
            'mollieSubscriptionId' => null,
            'lastRemindedAt' => null,
            'canceledAt' => null,
            'status' => SubscriptionStatus::PENDING->value,
            'description' => $description,
            'amount' => $amount,
            'currencyId' => $order->getCurrencyId(),
            'metadata' => $this->buildMetadataArray($primaryLineItem, $order->getOrderDate()),
            'orderId' => $order->getId(),
            'orderVersionId' => $order->getVersionId(),
            'salesChannelId' => $order->getSalesChannelId(),
            'totalRounding' => $totalRoundingValue,
            'itemRounding' => $itemRoundingValue,
            'order' => [
                'id' => $order->getId(),
                'orderVersionId' => $order->getVersionId(),
                'tags' => [
                    [
                        'id' => SubscriptionTag::ID,
                    ],
                ],
            ],
            'historyEntries' => [
                [
                    'statusFrom' => '',
                    'statusTo' => SubscriptionStatus::PENDING->value,
                    'comment' => 'created',
                ],
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    private function buildAddressData(OrderAddressEntity $address, string $subscriptionId): array
    {
        $data = [
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
        $data['id'] = Uuid::fromStringToHex(implode('-', array_values($data)));

        return $data;
    }

    /**
     * @return array<mixed>
     */
    private function buildMetadataArray(OrderLineItemEntity $lineItem, \DateTimeInterface $orderDate): array
    {
        /** @var ?Product $productExtension */
        $productExtension = $lineItem->getExtension(Mollie::EXTENSION);
        if (! $productExtension instanceof Product) {
            return [];
        }

        $repetitions = 0;
        if ($productExtension->getRepetition() > 0) {
            $repetitions = $productExtension->getRepetition() - 1;
        }

        $startDate = \DateTime::createFromFormat('Y-m-d', $orderDate->format('Y-m-d'));
        if (! $startDate instanceof \DateTimeInterface) {
            throw new \RuntimeException('Failed to create date object from order date');
        }

        $interval = $productExtension->getInterval();
        $startDate->modify('+' . (string) $interval);

        return (new SubscriptionMetadata(
            $startDate->format('Y-m-d'),
            $interval->getIntervalValue(),
            $interval->getIntervalUnit(),
            $repetitions
        ))->toArray();
    }
}
