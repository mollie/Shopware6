<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Exception\OrderCurrencyNotFoundException;
use Kiener\MolliePayments\Exception\OrderCustomerNotFoundException;
use Kiener\MolliePayments\Exception\OrderDeliveriesNotFoundException;
use Kiener\MolliePayments\Exception\OrderDeliveryNotFoundException;
use Kiener\MolliePayments\Exception\OrderLineItemsNotFoundException;
use Kiener\MolliePayments\Service\CustomerService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class OrderDataExtractor
{
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var CustomerService
     */
    private $customerService;


    /**
     * @param LoggerInterface $loggerService
     * @param CustomerService $customerService
     */
    public function __construct(LoggerInterface $loggerService, CustomerService $customerService)
    {
        $this->logger = $loggerService;
        $this->customerService = $customerService;
    }

    public function extractCustomer(OrderEntity $order, SalesChannelContext $salesChannelContext): CustomerEntity
    {
        $orderCustomer = $order->getOrderCustomer();

        if (!$orderCustomer instanceof OrderCustomerEntity) {
            $this->logger->critical(
                sprintf('Could not fetch customer from order with id %s', $order->getId())
            );

            throw new OrderCustomerNotFoundException($order->getId());
        }

        $enrichedCustomer = $this->customerService->getCustomer(
            (string)$orderCustomer->getCustomerId(),
            $salesChannelContext->getContext()
        );

        if (!$enrichedCustomer instanceof CustomerEntity) {
            $this->logger->critical(
                sprintf('Could not find customer with id %s in database', $order->getId())
            );

            throw new OrderCustomerNotFoundException($order->getId());
        }

        return $enrichedCustomer;
    }

    public function extractCurrency(OrderEntity $orderEntity, SalesChannelContext $salesChannelContext): CurrencyEntity
    {
        $currency = $orderEntity->getCurrency();

        if (!$currency instanceof CurrencyEntity) {
            $this->logger->critical(
                sprintf('Could not fetch currency from order with id %s', $orderEntity->getId())
            );

            throw new OrderCurrencyNotFoundException($orderEntity->getId());
        }

        return $currency;
    }

    public function extractLocale(OrderEntity $orderEntity, SalesChannelContext $salesChannelContext): ?LocaleEntity
    {
        $orderLocale = $orderEntity->getLanguage()->getLocale();

        if ($orderLocale instanceof LocaleEntity) {
            return $orderLocale;
        }

        // try to fetch locale information from saleschannel
        $salesChannelLanguage = $salesChannelContext->getSalesChannel()->getLanguage();

        if (!$salesChannelLanguage instanceof LanguageEntity) {
            return null;
        }

        return $salesChannelLanguage->getLocale();
    }

    public function extractDeliveries(OrderEntity $orderEntity, Context $context): OrderDeliveryCollection
    {
        $deliveries = $orderEntity->getDeliveries();

        if (!$deliveries instanceof OrderDeliveryCollection) {
            $this->logger->critical(
                sprintf('Could not fetch deliveries from order with id %s', $orderEntity->getId())
            );

            throw new OrderDeliveriesNotFoundException($orderEntity->getId());
        }

        return $deliveries;
    }

    public function extractDelivery(OrderEntity $orderEntity, Context $context): OrderDeliveryEntity
    {
        $deliveries = $this->extractDeliveries($orderEntity, $context);

        /**
         * TODO: In future Shopware versions there might be multiple deliveries. There is support for multiple deliveries
         * but as of writing only one delivery is created per order, which is why we use first() here.
         */
        $delivery = $deliveries->first();

        if (!$delivery instanceof OrderDeliveryEntity) {
            $this->logger->critical(
                sprintf('Could not fetch deliveries from order with id %s', $orderEntity->getId())
            );

            throw new OrderDeliveryNotFoundException($orderEntity->getId());
        }

        return $delivery;
    }

    public function extractLineItems(OrderEntity $orderEntity, Context $context): OrderLineItemCollection
    {
        $lineItems = $orderEntity->getLineItems();

        if (!$lineItems instanceof OrderLineItemCollection) {
            $this->logger->critical(
                sprintf('Could not fetch line items from order with id %s', $orderEntity->getId())
            );

            throw new OrderLineItemsNotFoundException($orderEntity->getId());
        }

        return $lineItems;
    }
}
