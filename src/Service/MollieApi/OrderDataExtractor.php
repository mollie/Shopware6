<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Exception\OrderCurrencyNotFound;
use Kiener\MolliePayments\Exception\OrderCustomerNotFound;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\LoggerService;
use Monolog\Logger;
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
     * @var LoggerService
     */
    private $loggerService;
    /**
     * @var CustomerService
     */
    private $customerService;

    public function __construct(LoggerService $loggerService, CustomerService $customerService)
    {

        $this->loggerService = $loggerService;
        $this->customerService = $customerService;
    }

    public function extractCustomer(OrderEntity $order, SalesChannelContext $salesChannelContext): CustomerEntity
    {
        $orderCustomer = $order->getOrderCustomer();

        if (!$orderCustomer instanceof OrderCustomerEntity) {
            $this->loggerService->addEntry(
                sprintf('Could not fetch customer form order with id %s', $order->getId()),
                $salesChannelContext->getContext(),
                null,
                [],
                Logger::CRITICAL
            );

            throw new OrderCustomerNotFound($order->getId());
        }

        $enrichedCustomer = $this->customerService->getCustomer(
            $orderCustomer->getCustomerId(),
            $salesChannelContext->getContext()
        );

        if (!$enrichedCustomer instanceof CustomerEntity) {
            $this->loggerService->addEntry(
                sprintf('Could not fetch customer form order with id %s', $order->getId()),
                $salesChannelContext->getContext(),
                null,
                [],
                Logger::CRITICAL
            );

            throw new OrderCustomerNotFound($order->getId());
        }

        return $enrichedCustomer;
    }

    public function extractCurrency(OrderEntity $orderEntity, SalesChannelContext $salesChannelContext): CurrencyEntity
    {
        $currency = $orderEntity->getCurrency();

        if (!$currency instanceof CurrencyEntity) {
            $this->loggerService->addEntry(
                sprintf('Could not fetch currency form order with id %s', $orderEntity->getId()),
                $salesChannelContext->getContext(),
                null,
                [],
                Logger::CRITICAL
            );

            throw new OrderCurrencyNotFound($orderEntity->getId());
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
