<?php

namespace Kiener\MolliePayments\Components\Subscription;

use Kiener\MolliePayments\Components\Subscription\Builder\MollieDataBuilder;
use Kiener\MolliePayments\Components\Subscription\Builder\SubscriptionBuilder;
use Kiener\MolliePayments\Components\Subscription\Repository\SubscriptionRepository;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Kiener\MolliePayments\Service\CustomerService;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SubscriptionManager
{

    /**
     * @var SubscriptionBuilder
     */
    private $builderSubscription;

    /**
     * @var MollieDataBuilder
     */
    private $builderMollie;

    /**
     * @var SubscriptionRepository
     */
    private $repoSubscriptions;

    /**
     * @var CustomerService
     */
    private $customers;

    /**
     * @var MollieGatewayInterface
     */
    private $gwMollie;


    /**
     * @param SubscriptionRepository $repoSubscriptions
     * @param MollieDataBuilder $definitionBuilder
     * @param SubscriptionBuilder $subscriptionBuilder
     * @param CustomerService $customers
     * @param MollieGatewayInterface $gatewayMollie
     */
    public function __construct(SubscriptionRepository $repoSubscriptions, MollieDataBuilder $definitionBuilder, SubscriptionBuilder $subscriptionBuilder, CustomerService $customers, MollieGatewayInterface $gatewayMollie)
    {
        $this->repoSubscriptions = $repoSubscriptions;
        $this->builderMollie = $definitionBuilder;
        $this->builderSubscription = $subscriptionBuilder;
        $this->customers = $customers;
        $this->gwMollie = $gatewayMollie;
    }


    /**
     * @param OrderEntity $order
     * @param SalesChannelContext $context
     * @throws \Exception
     */
    public function createSubscriptions(OrderEntity $order, SalesChannelContext $context): void
    {
        # extract and build our subscription items
        # from the current order entity.
        # this will lead to a separate subscription
        # for each subscription product in that order
        $orderSubscriptions = $this->builderSubscription->buildSubscriptions($order);

        foreach ($orderSubscriptions as $subscription) {
            $this->repoSubscriptions->insertSubscription($subscription, $context->getContext());
        }

        # TODO mark order as subscription order!
    }

    /**
     * @param OrderEntity $order
     * @param SalesChannelContext $context
     */
    public function confirmSubscriptions(OrderEntity $order, SalesChannelContext $context): void
    {
        $customerId = $this->customers->getMollieCustomerId(
            $order->getOrderCustomer()->getCustomerId(),
            $order->getSalesChannelId(),
            $context->getContext()
        );


        # switch out client to the correct sales channel
        $this->gwMollie->switchClient($context->getSalesChannel()->getId());


        $pendingSubscriptions = $this->repoSubscriptions->getPendingSubscriptions($order->getId(), $context->getContext());

        foreach ($pendingSubscriptions as $subscription) {

            # convert our subscription into a mollie definition
            $mollieData = $this->builderMollie->buildDefinition($subscription);

            # create the subscription in Mollie.
            # this is important to really start the subscription process
            $mollieSubscription = $this->gwMollie->createSubscription($customerId, $mollieData);

            # save our subscription in our local database
            $this->repoSubscriptions->confirmSubscription(
                $subscription->getId(),
                $mollieSubscription->id,
                $mollieSubscription->customerId,
                $context->getContext()
            );
        }
    }

    /**
     * @param OrderEntity $order
     * @param SalesChannelContext $context
     */
    public function cancelPendingSubscriptions(OrderEntity $order, SalesChannelContext $context): void
    {
        # TODO
    }

    /**
     * @param string $subscriptionId
     * @param string $mollieCustomerId
     * @param SalesChannelContext $context
     * @return void
     */
    public function cancelSubscription(string $subscriptionId, string $mollieCustomerId, SalesChannelContext $context): void
    {
        $this->gwMollie->switchClient($context->getSalesChannel()->getId());

        try {

            $this->gwMollie->cancelSubscription($subscriptionId, $mollieCustomerId);

        } catch (\Exception $exception) {

        } finally {

            $this->cancelSubscriptionReference($mollieCustomerId, $subscriptionId, $context);
        }
    }

    /**
     * @param string $customerId
     * @param string $subscriptionId
     * @param SalesChannelContext $context
     * @return void
     */
    private function cancelSubscriptionReference(string $customerId, string $subscriptionId, SalesChannelContext $context)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mollieCustomerId', $customerId));
        $criteria->addFilter(new EqualsFilter('subscriptionId', $subscriptionId));


        $subscription = $this->repoSubscriptions->search($criteria, $context->getContext())->first();

        $data = [
            [
                'id' => $subscription->getId(),
                'status' => 'canceled'
            ]
        ];

        $this->repoSubscriptions->upsert($data, $context->getContext());
    }

}
