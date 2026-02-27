<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Mollie\Shopware\Component\Transaction\Exception\OrderWithoutDeliveriesException;
use Mollie\Shopware\Component\Transaction\Exception\OrderWithoutTransactionException;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartOrderRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartOrderRoute;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CopyOrderService implements CopyOrderServiceInterface
{
    /**
     * @param EntityRepository<OrderCollection<OrderEntity>> $orderRepository
     */
    public function __construct(
        #[Autowire(service: 'order.repository')]
        private readonly EntityRepository $orderRepository,
        private readonly OrderConverter $orderConverter,
        #[Autowire(service: CartOrderRoute::class)]
        private readonly AbstractCartOrderRoute $cartOrderRoute,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public function copy(SubscriptionDataStruct $subscriptionData, Payment $payment, Context $context): OrderTransactionEntity
    {
        $order = $subscriptionData->getOrder();
        $subscriptionId = $subscriptionData->getSubscription()->getId();
        $subscriptionBillingAddress = $subscriptionData->getBillingAddress();
        $subscriptionShippingAddress = $subscriptionData->getShippingAddress();

        $logData = [
            'orderId' => $order->getId(),
            'orderNumber' => $order->getOrderNumber(),
        ];

        $this->logger->info('Start to copy old order', $logData);
        $newOrder = $this->copyOrder($order, $context);

        $orderNumber = (string) $newOrder->getOrderNumber();
        $logData['newOrderNumber'] = $orderNumber;

        $this->logger->info('New order was created', $logData);

        $firstTransaction = $newOrder->getTransactions()?->first();

        if (! $firstTransaction instanceof OrderTransactionEntity) {
            $this->logger->error('The order transaction entity was not found', $logData);
            throw new OrderWithoutTransactionException($newOrder->getId());
        }
        $orderDeliveries = $order->getDeliveries();
        if (! $orderDeliveries instanceof OrderDeliveryCollection) {
            throw new OrderWithoutDeliveriesException($newOrder->getId());
        }

        $orderData = $this->getOrderData($subscriptionId, $newOrder, $orderDeliveries, $firstTransaction, $subscriptionBillingAddress, $subscriptionShippingAddress, $payment);

        $this->orderRepository->upsert([$orderData], $context);

        $payment->setShopwareTransaction($firstTransaction);

        return $firstTransaction;
    }

    private function copyOrder(OrderEntity $order, Context $context): OrderEntity
    {
        $salesChannelContext = $this->orderConverter->assembleSalesChannelContext($order, $context);

        $cart = $this->orderConverter->convertToCart($order, $context);

        $cart->removeExtension(OrderConverter::ORIGINAL_ID);
        $cart->removeExtension(OrderConverter::ORIGINAL_ORDER_NUMBER);

        $cart->removeExtension('originalPrimaryOrderDelivery');
        $cart->removeExtension('originalPrimaryOrderTransaction');

        $deliveries = new DeliveryCollection();

        foreach ($cart->getDeliveries() as $delivery) {
            foreach ($delivery->getPositions() as $position) {
                $position->removeExtension(OrderConverter::ORIGINAL_ID);
                $position->getLineItem()->removeExtension(OrderConverter::ORIGINAL_ID);
            }

            $delivery = new Delivery(
                $delivery->getPositions(),
                $delivery->getDeliveryDate(),
                $delivery->getShippingMethod(),
                $salesChannelContext->getShippingLocation(),
                $delivery->getShippingCosts()
            );
            $deliveries->add($delivery);
        }
        $cart->setDeliveries($deliveries);

        foreach ($cart->getLineItems() as $lineItem) {
            $lineItem->removeExtension(OrderConverter::ORIGINAL_ID);
        }

        $orderResponse = $this->cartOrderRoute->order($cart, $salesChannelContext, (new DataBag())->toRequestDataBag());

        return $orderResponse->getOrder();
    }

    /**
     * @return array<mixed>
     */
    private function getOrderData(string $subscriptionId, OrderEntity $newOrder, OrderDeliveryCollection $deliveryCollection, OrderTransactionEntity $firstTransaction, SubscriptionAddressEntity $subscriptionBillingAddress, SubscriptionAddressEntity $subscriptionShippingAddress, Payment $molliePayment): array
    {
        $billingAddress = $this->getAddressData($subscriptionBillingAddress);
        $billingAddress['id'] = $newOrder->getBillingAddressId();
        $shippingAddress = $this->getAddressData($subscriptionShippingAddress);

        $deliveries = [];

        foreach ($deliveryCollection as $delivery) {
            $shippingAddress['id'] = $delivery->getShippingOrderAddressId();
            $deliveries[] = [
                'id' => $delivery->getId(),
                'shippingOrderAddress' => $shippingAddress
            ];
        }

        return [
            'id' => $firstTransaction->getOrderId(),
            'transactions' => [
                [
                    'id' => $firstTransaction->getId(),
                    'customFields' => [
                        Mollie::EXTENSION => $molliePayment->toArray()
                    ]
                ]
            ],
            'billingAddress' => $billingAddress,
            'deliveries' => $deliveries,
            'tags' => [
                [
                    'id' => SubscriptionTag::ID
                ]
            ],
            'customFields' => [
                Mollie::EXTENSION => [
                    'swSubscriptionId' => $subscriptionId,
                ]
            ]
        ];
    }

    /**
     * @return array<mixed>
     */
    private function getAddressData(SubscriptionAddressEntity $address): array
    {
        $addressData = [
            'title' => $address->getTitle(),
            'firstName' => $address->getFirstName(),
            'lastName' => $address->getLastName(),
            'salutationId' => $address->getSalutationId(),
            'countryId' => $address->getCountryId(),
            'countryStateId' => $address->getCountryStateId(),
            'company' => $address->getCompany(),
            'department' => $address->getDepartment(),
            'street' => $address->getStreet(),
            'zipcode' => $address->getZipcode(),
            'city' => $address->getCity(),
            'vatId' => $address->getVatId(),
            'phoneNumber' => $address->getPhoneNumber(),
            'additionalAddressLine1' => $address->getAdditionalAddressLine1(),
            'additionalAddressLine2' => $address->getAdditionalAddressLine2(),
        ];
        $addressData['customFields'] = [
            Mollie::EXTENSION => [
                'subscriptionAddressId' => $address->getId(),
            ]
        ];

        return $addressData;
    }
}
