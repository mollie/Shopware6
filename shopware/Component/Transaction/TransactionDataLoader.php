<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Transaction;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * In order to create or load a payment, some data is required from shopware, since many fields are nullable,
 * i have to check for null on multiple parts
 * with this class i want to check once if all data is set and then use data
 */
final class TransactionDataLoader implements TransactionDataLoaderInterface
{
    /**
     * @param EntityRepository<OrderTransactionCollection<OrderTransactionEntity>> $orderTransactionRepository
     */
    public function __construct(
        #[Autowire(service: 'order_transaction.repository')]
        private EntityRepository $orderTransactionRepository,
    ) {
    }

    public function findById(string $transactionId, Context $context): TransactionDataStruct
    {
        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation('order.orderCustomer.salutation');
        $criteria->addAssociation('order.orderCustomer.customer');
        $criteria->addAssociation('order.addresses.country');
        $criteria->addAssociation('order.deliveries.shippingAddress');
        $criteria->addAssociation('order.billingAddress.country');
        $criteria->addAssociation('order.language.locale');
        $criteria->addAssociation('order.customer');
        $criteria->addAssociation('order.salesChannel');
        $criteria->addAssociation('order.lineItems.product.media');
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
        $language = $order->getLanguage();
        if (! $language instanceof LanguageEntity) {
            throw TransactionDataException::orderWithoutLanguage($order->getId());
        }

        $currency = $order->getCurrency();
        if (! $currency instanceof CurrencyEntity) {
            throw TransactionDataException::orderWithoutCurrency($order->getId());
        }
        /** @var OrderDeliveryEntity $firstDeliveryLine */
        $firstDeliveryLine = $deliveries->first();

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
}
