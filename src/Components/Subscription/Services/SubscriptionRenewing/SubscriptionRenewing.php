<?php

namespace Kiener\MolliePayments\Components\Subscription\Services\SubscriptionRenewing;


use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Service\OrderService;
use Mollie\Api\Resources\Payment;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SubscriptionRenewing
{

    /**
     * @var NumberRangeValueGeneratorInterface
     */
    private $numberRanges;

    /**
     * @var EntityRepositoryInterface
     */
    private $repoOrders;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var OrderCloneService
     */
    private $orderCloneService;


    /**
     * @param NumberRangeValueGeneratorInterface $numberRanges
     * @param EntityRepositoryInterface $repoOrders
     * @param OrderService $orderService
     * @param OrderCloneService $orderCloneService
     */
    public function __construct(NumberRangeValueGeneratorInterface $numberRanges, EntityRepositoryInterface $repoOrders, OrderService $orderService, OrderCloneService $orderCloneService)
    {
        $this->numberRanges = $numberRanges;
        $this->repoOrders = $repoOrders;
        $this->orderService = $orderService;
        $this->orderCloneService = $orderCloneService;
    }


    /**
     * @param SubscriptionEntity $subscription
     * @param Payment $molliePayment
     * @param SalesChannelContext $context
     * @return OrderEntity
     */
    public function renewSubscription(SubscriptionEntity $subscription, Payment $molliePayment, SalesChannelContext $context): OrderEntity
    {
        $order = $this->getOrder($subscription->getOrderId(), $context->getContext());

        if (!$order instanceof OrderEntity) {
            throw new EntityNotFoundException('order', $subscription->getOrderId());
        }

        # get the next order number
        $newOrderNumber = $this->numberRanges->getValue('order', $context->getContext(), $subscription->getSalesChannelId());

        # now let's clone our previous order and create a new one from it
        $orderId = $this->orderCloneService->createNewOrder($order, $newOrderNumber, $context->getContext());

        $order = $this->getOrder($orderId, $context->getContext());


        # also make sure to update our metadata
        # that is stored in the custom fields of the
        # Shopware order and its transactions
        $this->orderService->updateMollieData(
            $order,
            $order->getTransactions()->last()->getId(),
            '',
            $subscription->getId(),
            $subscription->getMollieId(),
            $molliePayment,
            $context
        );

        return $order;
    }

    /**
     * @param string|null $orderId
     * @param Context $context
     * @return OrderEntity|null
     */
    private function getOrder(?string $orderId, Context $context): ?OrderEntity
    {
        $criteria = (new Criteria([$orderId]));

        $criteria->addAssociation('addresses');
        $criteria->addAssociation('transactions.stateMachineState');
        $criteria->addAssociation('transactions.paymentMethod');
        $criteria->addAssociation('orderCustomer.customer');
        $criteria->addAssociation('orderCustomer.salutation');
        $criteria->addAssociation('transactions.paymentMethod.appPaymentMethod.app');
        $criteria->addAssociation('language');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('deliveries.positions.orderLineItem');
        $criteria->addAssociation('deliveries.shippingOrderAddress');
        $criteria->addAssociation('deliveries.shippingMethod');
        $criteria->addAssociation('deliveries.shippingOrderAddress.country');
        $criteria->addAssociation('billingAddress.country');
        $criteria->addAssociation('lineItems');

        return $this->repoOrders->search($criteria, $context)->get($orderId);
    }


}
