<?php

namespace MolliePayments\Tests\Fakes;

use Kiener\MolliePayments\Compatibility\Gateway\CompatibilityGatewayInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;


class FakeCompatibilityGateway implements CompatibilityGatewayInterface
{

    /**
     * @param SalesChannelContext $context
     * @return string
     */
    public function getSalesChannelID(SalesChannelContext $context): string
    {
        return '';
    }

    /**
     * @param string $salesChannelID
     * @param string $token
     * @return SalesChannelContext
     */
    public function getSalesChannelContext(string $salesChannelID, string $token): SalesChannelContext
    {
    }

    /**
     * @return string
     */
    public function getLineItemPromotionType(): string
    {
        return LineItem::PROMOTION_LINE_ITEM_TYPE;
    }

}
