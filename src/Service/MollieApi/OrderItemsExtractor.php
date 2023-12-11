<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Exception\OrderCurrencyNotFoundException;
use Kiener\MolliePayments\Exception\OrderCustomerNotFoundException;
use Kiener\MolliePayments\Exception\OrderLineItemsNotFoundException;
use Kiener\MolliePayments\Service\CustomerService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class OrderItemsExtractor
{

    /**
     * @param OrderEntity $orderEntity
     * @return OrderLineItemCollection
     */
    public function extractLineItems(OrderEntity $orderEntity): OrderLineItemCollection
    {
        $lineItems = $orderEntity->getLineItems();

        if (!$lineItems instanceof OrderLineItemCollection) {
            throw new OrderLineItemsNotFoundException($orderEntity->getId());
        }

        return $lineItems;
    }
}
