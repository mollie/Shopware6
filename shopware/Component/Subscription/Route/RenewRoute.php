<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionCollection;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGateway;
use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGatewayInterface;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Payment\Route\AbstractWebhookRoute as AbstractPaymentWebhookRoute;
use Mollie\Shopware\Component\Payment\Route\WebhookResponse;
use Mollie\Shopware\Component\Payment\Route\WebhookRoute as PaymentWebhookRoute;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Subscription\SubscriptionTag;
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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => false, 'auth_enabled' => false])]
final class RenewRoute extends AbstractRenewRoute
{
    /**
     * @param EntityRepository<SubscriptionCollection<SubscriptionEntity>> $subscriptionRepository
     * @param EntityRepository<OrderCollection<OrderEntity>> $orderRepository
     */
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        #[Autowire(service: 'mollie_subscription.repository')]
        private readonly EntityRepository $subscriptionRepository,
        #[Autowire(service: 'order.repository')]
        private readonly EntityRepository $orderRepository,
        #[Autowire(service: SubscriptionGateway::class)]
        private readonly SubscriptionGatewayInterface $subscriptionGateway,
        #[Autowire(service: MollieGateway::class)]
        private readonly MollieGatewayInterface $mollieGateway,
        private readonly OrderConverter $orderConverter,
        #[Autowire(service: CartOrderRoute::class)]
        private readonly AbstractCartOrderRoute $cartOrderRoute,
        #[Autowire(service: PaymentWebhookRoute::class)]
        private readonly AbstractPaymentWebhookRoute $paymentWebhookRoute,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    public function getDecorated(): AbstractRenewRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/api/mollie/webhook/subscription/{subscriptionId}/renew', name: 'api.mollie.webhook_subscription_renew', methods: ['GET', 'POST'])]
    public function renew(string $subscriptionId, Request $request, Context $context): WebhookResponse
    {
        $subscriptionId = strtolower($subscriptionId);
        $molliePaymentId = (string) $request->get('id', '');
        $logData = [
            'subscriptionId' => $subscriptionId,
            'molliePaymentId' => $molliePaymentId,
            'data' => [
                'postData' => $request->request->all(),
                'queryData' => $request->query->all(),
            ]
        ];
        $this->logger->info('Subscription renew started', $logData);

        if (strlen($molliePaymentId) === 0) {
            $this->logger->error('Subscription renew was triggered without required data', $logData);
            throw WebhookException::paymentIdNotProvided($subscriptionId);
        }

        $criteria = new Criteria([$subscriptionId]);
        $criteria->addAssociation('billingAddress');
        $criteria->addAssociation('shippingAddress');
        $criteria->addAssociation('order.transactions');
        $criteria->addAssociation('order.lineItems');
        $criteria->addAssociation('order.deliveries.positions.orderLineItem');
        $criteria->addAssociation('order.deliveries.shippingMethod');
        $criteria->addAssociation('order.deliveries.shippingOrderAddress.country');
        $criteria->addAssociation('order.orderCustomer.customer');
        $criteria->setLimit(1);
        $searchResult = $this->subscriptionRepository->search($criteria, $context);

        $subscriptionEntity = $searchResult->first();
        if (! $subscriptionEntity instanceof SubscriptionEntity) {
            $this->logger->error('Subscription was not found', $logData);
            throw RenewException::subscriptionNotFound($subscriptionId);
        }

        $order = $subscriptionEntity->getOrder();
        if (! $order instanceof OrderEntity) {
            $this->logger->error('Subscription without order loaded', $logData);
            throw RenewException::subscriptionWithoutOrder($subscriptionId);
        }
        $subscriptionBillingAddress = $subscriptionEntity->getBillingAddress();
        if (! $subscriptionBillingAddress instanceof SubscriptionAddressEntity) {
            $this->logger->error('Subscription billing address was not found', $logData);
            throw RenewException::subscriptionWithoutAddress($subscriptionId);
        }
        $subscriptionShippingAddress = $subscriptionEntity->getShippingAddress();
        if (! $subscriptionShippingAddress instanceof SubscriptionAddressEntity) {
            $this->logger->error('Subscription shipping address was not found', $logData);
            throw RenewException::subscriptionWithoutAddress($subscriptionId);
        }

        $salesChannelId = $order->getSalesChannelId();
        $orderNumber = (string) $order->getOrderNumber();
        $mollieCustomerId = $subscriptionEntity->getMollieCustomerId();
        $mollieSubscriptionId = $subscriptionEntity->getMollieId();
        $subscriptionSettings = $this->settingsService->getSubscriptionSettings($salesChannelId);

        $logData['orderNumber'] = $orderNumber;
        $logData['salesChannelId'] = $salesChannelId;
        $logData['mollieCustomerId'] = $mollieCustomerId;
        $logData['mollieSubscriptionId'] = $mollieSubscriptionId;
        if (! $subscriptionSettings->isEnabled()) {
            $this->logger->error('Subscription renew not possible, subscriptions are disabled for this sales channel', $logData);
            throw RenewException::subscriptionsDisabled($subscriptionId, $salesChannelId);
        }

        $subscription = $this->subscriptionGateway->getSubscription($mollieSubscriptionId, $mollieCustomerId, $orderNumber, $salesChannelId);
        $molliePayment = $this->mollieGateway->getPayment($molliePaymentId, $orderNumber, $salesChannelId);
        $environmentSettings = $this->settingsService->getEnvironmentSettings();

        if (! $environmentSettings->isDevMode() && $molliePayment->getSubscriptionId() !== $subscription->getId()) {
            $this->logger->error('The provided mollie payments ID does not belong to the subscription', $logData);
            throw RenewException::invalidPaymentId($subscriptionId, $molliePaymentId);
        }

        if ($molliePayment->getStatus()->isFailed() && $subscriptionSettings->isSkipIfFailed()) {
            return new WebhookResponse($molliePayment);
        }

        $subscriptionHistories = [];

        if ($subscription->getStatus() === SubscriptionStatus::SKIPPED) {
            $this->logger->info('Subscription was skipped, changed to resumed', $logData);
            $subscriptionHistories[] = [
                'statusFrom' => $subscription->getStatus()->value,
                'statusTo' => SubscriptionStatus::RESUMED->value,
                'mollieId' => $subscription->getId(),
                'comment' => 'resumed'
            ];
            $subscription->setStatus(SubscriptionStatus::RESUMED);
        }

        $metaData = $subscriptionEntity->getMetadata();
        $isLimited = $metaData->getTimes() > 0;
        $metaDataArray = $metaData->toArray();

        if ($isLimited > 0 && $subscription->getTimesRemaining() > 0) {
            $metaDataArray['times'] = $subscription->getTimesRemaining();
        }

        $this->logger->info('Start to copy old order', $logData);
        $newOrder = $this->copyOrder($order, $context);
        $logData['oldOrderNumber'] = $orderNumber;
        $orderNumber = (string) $newOrder->getOrderNumber();
        $logData['orderNumber'] = $orderNumber;

        $this->logger->info('New order was created', $logData);

        $firstTransaction = $newOrder->getTransactions()?->first();

        if (! $firstTransaction instanceof OrderTransactionEntity) {
            $this->logger->error('The order transaction entity was not found', $logData);
            throw RenewException::orderWithoutTransaction($subscriptionId, $orderNumber);
        }
        $orderDeliveries = $order->getDeliveries();
        if (! $orderDeliveries instanceof OrderDeliveryCollection) {
            throw RenewException::orderWithoutDeliveries($subscriptionId, $orderNumber);
        }

        $orderData = $this->getOrderData($subscriptionId, $newOrder, $orderDeliveries, $firstTransaction, $subscriptionBillingAddress, $subscriptionShippingAddress, $molliePayment);

        $this->orderRepository->upsert([$orderData], $context);

        $subscriptionHistories[] = [
            'statusFrom' => $subscription->getStatus()->value,
            'statusTo' => SubscriptionStatus::ACTIVE->value,
            'mollieId' => $subscription->getId(),
            'comment' => 'renewed'
        ];

        $this->subscriptionRepository->upsert([[
            'id' => $subscriptionId,
            'metaData' => $metaDataArray,
            'mandateId' => (string) $molliePayment->getMandateId(),
            'nextPaymentAt' => $subscription->getNextPaymentDate()->format('Y-m-d'),
            'historyEntries' => $subscriptionHistories
        ]], $context);

        if ($isLimited && $subscription->getTimesRemaining() <= 0) {
            $this->logger->info('Subscription had limited amount, it is finished completely now', $logData);
        }
        $this->logger->info('Subscription renew finished', $logData);

        return $this->paymentWebhookRoute->notify($firstTransaction->getId(), $context);
    }

    /**
     * @return array<mixed>
     */
    public function getOrderData(string $subscriptionId, OrderEntity $newOrder, OrderDeliveryCollection $deliveryCollection, OrderTransactionEntity $firstTransaction, SubscriptionAddressEntity $subscriptionBillingAddress, SubscriptionAddressEntity $subscriptionShippingAddress, Payment $molliePayment): array
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
