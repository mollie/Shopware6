<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;


use Kiener\MolliePayments\Exception\OrderCurrencyNotFound;
use Kiener\MolliePayments\Exception\OrderCustomerNotFound;
use Kiener\MolliePayments\Service\LoggerService;
use Monolog\Logger;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @copyright 2021 dasistweb GmbH (https://www.dasistweb.de)
 */
class OrderDataExtractor
{
    /**
     * @var LoggerService
     */
    private $loggerService;

    public function __construct(LoggerService $loggerService)
    {

        $this->loggerService = $loggerService;
    }

    public function extractCustomer(OrderEntity $order, SalesChannelContext $salesChannelContext): OrderCustomerEntity
    {
        $orderCustomer = $order->getOrderCustomer();

        if ($orderCustomer instanceof OrderCustomerEntity) {
            $this->loggerService->addEntry(
                sprintf('Could not fetch customer form order with id %s', $orderEntity->getId()),
                $salesChannelContext->getContext(),
                null,
                [],
                Logger::CRITICAL
            );

            throw new OrderCustomerNotFound($orderEntity->getId());
        }

        return $orderCustomer;
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

    public function extractLocaleCode(OrderEntity $orderEntity, SalesChannelContext $salesChannelContext): ?string
    {
        $orderLocale = $orderEntity->getLanguage()->getLocale();

        if ($orderLocale instanceof LocaleEntity) {
            return $orderLocale->getCode();
        }

        // try to fetch locale information from saleschannel
        $salesChannelLanguage = $salesChannelContext->getSalesChannel()->getLanguage();

        if (!$salesChannelLanguage instanceof LanguageEntity) {
            return null;
        }

        $salesChannelLocale = $salesChannelLanguage->getLocale();

        if ($salesChannelLocale instanceof LocaleEntity) {
            return $salesChannelLocale->getCode();
        }

        return null;
    }
}
