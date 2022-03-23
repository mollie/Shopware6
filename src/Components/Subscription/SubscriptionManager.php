<?php

namespace Kiener\MolliePayments\Components\Subscription;

use Kiener\MolliePayments\Components\Subscription\Builder\MollieDataBuilder;
use Kiener\MolliePayments\Components\Subscription\Builder\SubscriptionBuilder;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Kiener\MolliePayments\Service\CustomerService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
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
    private $builderMollieDefinition;

    /**
     * @var EntityRepositoryInterface
     */
    private $repoSubscriptions;

    /**
     * @var CustomerService
     */
    private $customers;

    /**
     * @var MollieGatewayInterface
     */
    private $gatewayMollie;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param EntityRepositoryInterface $repoSubscriptions
     * @param MollieDataBuilder $definitionBuilder
     * @param SubscriptionBuilder $subscriptionBuilder
     * @param CustomerService $customers
     * @param MollieGatewayInterface $gatewayMollie
     * @param LoggerInterface $logger
     */
    public function __construct(EntityRepositoryInterface $repoSubscriptions, MollieDataBuilder $definitionBuilder, SubscriptionBuilder $subscriptionBuilder, CustomerService $customers, MollieGatewayInterface $gatewayMollie, LoggerInterface $logger)
    {
        $this->repoSubscriptions = $repoSubscriptions;
        $this->builderMollieDefinition = $definitionBuilder;
        $this->builderSubscription = $subscriptionBuilder;
        $this->customers = $customers;
        $this->gatewayMollie = $gatewayMollie;
        $this->logger = $logger;
    }

    /**
     * @return array
     */
    public function getAll(): array
    {
        return $this->gatewayMollie->getAllSubscriptions()->getArrayCopy();
    }

    /**
     * @param OrderEntity $order
     * @param SalesChannelContext $context
     * @return void
     * @throws \Exception
     */
    public function createSubscriptions(OrderEntity $order, SalesChannelContext $context): void
    {
        $customerId = $this->customers->getMollieCustomerId(
            $order->getOrderCustomer()->getCustomerId(),
            $order->getSalesChannelId(),
            $context->getContext()
        );


        # switch out client to the correct sales channel
        $this->gatewayMollie->switchClient($context->getSalesChannel()->getId());

        # extract and build our subscription items
        # from the current order entity.
        # this will lead to a separate subscription
        # for each subscription product in that order
        $orderSubscriptions = $this->builderSubscription->buildSubscriptions($order);

        foreach ($orderSubscriptions as $subscription) {

            try {

                # convert our subscription into a mollie definition
                $mollieData = $this->builderMollieDefinition->buildDefinition($subscription);

                # create the subscription in Mollie.
                #this is important to really start the subscription process
                $mollieSubscription = $this->gatewayMollie->createSubscription($customerId, $mollieData);

                # now update our local entity with the
                # new IDs that we get from mollie
                $subscription->setMollieData($customerId, $mollieSubscription->id);

                # save our subscription in our local database
                $this->saveSubscriptionEntity($subscription, $context);

            } catch (\Exception $exception) {

                $this->logger->emergency(
                    'Error when creating Subscription in Mollie for order: ' . $order->getOrderNumber(),
                    [
                        'error' => $exception->getMessage(),
                    ]
                );
            }
        }


        #   $this->repoOrderTransactions->upsert([[
        #       'id' => $event->getEntityId(),
        #       'customFields' => ['subscription_created' => date('Y-m-d')]
        #   ]], $event->getContext());
    }

    /**
     * @param string $subscriptionId
     * @param string $mollieCustomerId
     * @param SalesChannelContext $context
     * @return void
     */
    public function cancelSubscription(string $subscriptionId, string $mollieCustomerId, SalesChannelContext $context): void
    {
        $this->gatewayMollie->switchClient($context->getSalesChannel()->getId());

        try {

            $this->gatewayMollie->cancelSubscription($subscriptionId, $mollieCustomerId);

        } catch (\Exception $exception) {

        } finally {

            $this->cancelSubscriptionReference($mollieCustomerId, $subscriptionId, $context);
        }
    }

    /**
     * @param SubscriptionEntity $subscription
     * @param SalesChannelContext $context
     */
    private function saveSubscriptionEntity(SubscriptionEntity $subscription, SalesChannelContext $context)
    {
        $this->repoSubscriptions->create([
            [
                'id' => $subscription->getId(),
                'mollieCustomerId' => '22', #$subscription->getMollieCustomerId(),
                'mollieSubscriptionId' => '2', # $subscription->getMollieSubscriptionId(),
                'productId' => $subscription->getShopwareProductId(),
                'originalOrderId' => $subscription->getOriginalOrderId(),
                'salesChannelId' => $context->getSalesChannelId(),
                'description' => $subscription->getDescription(),
                'amount' => $subscription->getAmount(),
                'currency' => $subscription->getCurrencyIso()
            ]
        ],
            $context->getContext()
        );
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
