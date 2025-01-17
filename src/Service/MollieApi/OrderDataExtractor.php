<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Exception\OrderCurrencyNotFoundException;
use Kiener\MolliePayments\Exception\OrderCustomerNotFoundException;
use Kiener\MolliePayments\Service\CustomerService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
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
}
