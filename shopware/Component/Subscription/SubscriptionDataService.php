<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\Exception\SubscriptionNotFoundException;
use Mollie\Shopware\Component\Subscription\Exception\SubscriptionWithoutAddressException;
use Mollie\Shopware\Component\Subscription\Exception\SubscriptionWithoutOrderException;
use Mollie\Shopware\Component\Transaction\Exception\OrderWithoutCustomerException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SubscriptionDataService implements SubscriptionDataServiceInterface
{
    /**
     * @param EntityRepository<SubscriptionCollection<SubscriptionEntity>> $subscriptionRepository
     */
    public function __construct(
        #[Autowire(service: 'mollie_subscription.repository')]
        private EntityRepository $subscriptionRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public function findById(string $subscriptionId, Context $context): SubscriptionDataStruct
    {
        $logData = [
            'subscriptionId' => $subscriptionId,
        ];

        $criteria = new Criteria([$subscriptionId]);
        $criteria->addAssociation('billingAddress');
        $criteria->addAssociation('shippingAddress');
        $criteria->addAssociation('order.transactions');
        $criteria->addAssociation('order.lineItems');
        $criteria->addAssociation('order.deliveries.positions.orderLineItem');
        $criteria->addAssociation('order.deliveries.shippingMethod');
        $criteria->addAssociation('order.deliveries.shippingOrderAddress.country');
        $criteria->addAssociation('order.orderCustomer.customer');
        $criteria->getAssociation('order.transactions')
            ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING))
            ->setLimit(1)
        ;

        $criteria->setLimit(1);

        $searchResult = $this->subscriptionRepository->search($criteria, $context);

        $subscriptionEntity = $searchResult->first();
        if (! $subscriptionEntity instanceof SubscriptionEntity) {
            $this->logger->error('Subscription was not found', $logData);
            throw new SubscriptionNotFoundException($subscriptionId);
        }

        $order = $subscriptionEntity->getOrder();
        if (! $order instanceof OrderEntity) {
            $this->logger->error('Subscription without order loaded', $logData);
            throw new SubscriptionWithoutOrderException($subscriptionId);
        }
        $orderNumber = (string) $order->getOrderNumber();
        $orderId = $order->getId();

        $logData['orderNumber'] = $orderNumber;

        $customer = $order->getOrderCustomer()?->getCustomer();
        if (! $customer instanceof CustomerEntity) {
            $this->logger->error('Subscription order has no customer', $logData);
            throw new OrderWithoutCustomerException($orderId);
        }

        $subscriptionBillingAddress = $subscriptionEntity->getBillingAddress();
        if (! $subscriptionBillingAddress instanceof SubscriptionAddressEntity) {
            $this->logger->error('Subscription billing address was not found', $logData);
            throw new SubscriptionWithoutAddressException($subscriptionId);
        }
        $subscriptionShippingAddress = $subscriptionEntity->getShippingAddress();
        if (! $subscriptionShippingAddress instanceof SubscriptionAddressEntity) {
            $this->logger->error('Subscription shipping address was not found', $logData);
            throw new SubscriptionWithoutAddressException($subscriptionId);
        }

        return new SubscriptionDataStruct(
            $subscriptionEntity,
            $order,
            $customer,
            $subscriptionBillingAddress,
            $subscriptionShippingAddress
        );
    }
}
