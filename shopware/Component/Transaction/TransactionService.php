<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Transaction;

use Mollie\Shopware\Component\Mollie\Order as MollieOrder;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * In order to create or load a payment, some data is required from shopware, since many fields are nullable,
 * i have to check for null on multiple parts
 * with this class i want to check once if all data is set and then use data
 */
final class TransactionService implements TransactionServiceInterface
{
    /**
     * @param EntityRepository<OrderTransactionCollection<OrderTransactionEntity>> $orderTransactionRepository
     */
    public function __construct(
        #[Autowire(service: 'order_transaction.repository')]
        private EntityRepository $orderTransactionRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public function findById(string $transactionId, Context $context): TransactionDataStruct
    {
        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation('order.orderCustomer.salutation');
        $criteria->addAssociation('order.orderCustomer.customer.salutation');
        $criteria->addAssociation('order.orderCustomer.customer.country');
        $criteria->addAssociation('order.addresses.country');
        $criteria->addAssociation('order.primaryOrderDelivery.shippingOrderAddress.country');
        $criteria->addAssociation('order.deliveries.shippingOrderAddress.country');
        $criteria->addAssociation('order.deliveries.shippingMethod');
        $criteria->addAssociation('order.billingAddress.country');
        $criteria->addAssociation('order.language.locale');
        $criteria->addAssociation('order.customer.language');
        $criteria->addAssociation('order.currency');
        $criteria->addAssociation('order.salesChannel');
        $criteria->addAssociation('order.lineItems.product.media');
        $criteria->addAssociation('order.stateMachineState.stateMachine');
        $criteria->addAssociation('order.mollieSubscriptions');
        $criteria->addAssociation('order.transactions');
        $criteria->addAssociation('paymentMethod');

        $searchResult = $this->orderTransactionRepository->search($criteria, $context);
        $transactionEntity = $searchResult->first();

        if (! $transactionEntity instanceof OrderTransactionEntity) {
            throw TransactionDataException::transactionNotFound($transactionId);
        }

        $order = $transactionEntity->getOrder();
        if (! $order instanceof OrderEntity) {
            throw TransactionDataException::oderNotExists($transactionId);
        }
        $salesChannel = $order->getSalesChannel();
        if (! $salesChannel instanceof SalesChannelEntity) {
            throw TransactionDataException::orderWithoutSalesChannel($order->getId());
        }
        $deliveries = $order->getDeliveries();
        if (! $deliveries instanceof OrderDeliveryCollection) {
            throw TransactionDataException::orderWithoutDeliveries($order->getId());
        }
        /** @var ?OrderDeliveryEntity $firstDeliveryLine */
        $firstDeliveryLine = $deliveries->first();

        if (method_exists($order,'getPrimaryOrderDelivery')) {
            $firstDeliveryLine = $order->getPrimaryOrderDelivery();
        }

        if (! $firstDeliveryLine instanceof OrderDeliveryEntity) {
            throw TransactionDataException::orderWithoutDeliveries($order->getId());
        }

        $language = $order->getLanguage();
        /** @phpstan-ignore identical.alwaysFalse */
        if ($language === null) {
            throw TransactionDataException::orderWithoutLanguage($order->getId());
        }

        $currency = $order->getCurrency();
        if (! $currency instanceof CurrencyEntity) {
            throw TransactionDataException::orderWithoutCurrency($order->getId());
        }

        $shippingOrderAddress = $firstDeliveryLine->getShippingOrderAddress();
        if (! $shippingOrderAddress instanceof OrderAddressEntity) {
            throw TransactionDataException::orderDeliveryWithoutShippingAddress($order->getId(), $firstDeliveryLine->getId());
        }
        $billingAddress = $order->getBillingAddress();
        if (! $billingAddress instanceof OrderAddressEntity) {
            throw TransactionDataException::orderWithoutBillingAddress($order->getId());
        }
        $orderCustomer = $order->getOrderCustomer();
        if (! $orderCustomer instanceof OrderCustomerEntity) {
            throw TransactionDataException::orderWithoutCustomer($order->getId());
        }
        $customer = $orderCustomer->getCustomer();
        if (! $customer instanceof CustomerEntity) {
            throw TransactionDataException::orderWithoutCustomer($order->getId());
        }

        return new TransactionDataStruct(
            $transactionEntity,
            $order,
            $salesChannel,
            $customer,
            $shippingOrderAddress,
            $billingAddress,
            $currency,
            $language,
            $deliveries
        );
    }

    public function savePaymentExtension(string $transactionId, OrderEntity $order, Payment $payment, Context $context, ?MollieOrder $mollieOrder = null): EntityWrittenContainerEvent
    {
        $salesChannel = $order->getSalesChannelId();
        $orderNumber = $order->getOrderNumber();

        $this->logger->debug('Save payment information in Order Transaction', [
            'transactionId' => $transactionId,
            'data' => $payment->toArray(),
            'orderNumber' => $orderNumber,
            'salesChannelId' => $salesChannel,
        ]);

        $upsertArray = [
            'id' => $transactionId,
            'customFields' => [
                Mollie::EXTENSION => $payment->toArray(),
            ],
        ];

        if (!$mollieOrder instanceof MollieOrder) {
            return $this->orderTransactionRepository->upsert([$upsertArray], $context);
        }

        $shopwareLineItems = $order->getLineItems() ?? new OrderLineItemCollection();
        $filteredMollieLines = $mollieOrder->getLines()->filterByOrderLineItems($shopwareLineItems);

        $shopwareDeliveries = $order->getDeliveries() ?? new OrderDeliveryCollection();
        $filteredMollieDeliveryLines = $mollieOrder->getLines()->filterByDeliveries($shopwareDeliveries);

        if ($filteredMollieLines->count() === 0 && $filteredMollieDeliveryLines->count() === 0) {
            return $this->orderTransactionRepository->upsert([$upsertArray], $context);
        }

        $orderData = ['id' => $order->getId()];

        if ($filteredMollieLines->count() > 0) {
            $lineItemsData = [];

            foreach ($filteredMollieLines->getElements() as $mollieLine) {
                $shopwareLineItem = $shopwareLineItems->get($mollieLine->getShopwareLineItemId());
                $customFields = $shopwareLineItem->getCustomFields() ?? [];
                $customFields[Mollie::EXTENSION] = ['order_line_id' => $mollieLine->getId()];

                $lineItemsData[] = [
                    'id' => $mollieLine->getShopwareLineItemId(),
                    'customFields' => $customFields,
                ];
            }

            $orderData['lineItems'] = $lineItemsData;
        }

        if ($filteredMollieDeliveryLines->count() > 0) {
            $deliveriesData = [];

            foreach ($filteredMollieDeliveryLines->getElements() as $mollieLine) {
                $delivery = $shopwareDeliveries->get($mollieLine->getShopwareLineItemId());
                $customFields = $delivery->getCustomFields() ?? [];
                $customFields[Mollie::EXTENSION] = ['order_line_id' => $mollieLine->getId()];

                $deliveriesData[] = [
                    'id' => $mollieLine->getShopwareLineItemId(),
                    'customFields' => $customFields,
                ];
            }

            $orderData['deliveries'] = $deliveriesData;
        }

        $upsertArray['order'] = $orderData;

        return $this->orderTransactionRepository->upsert([$upsertArray], $context);
    }
}
