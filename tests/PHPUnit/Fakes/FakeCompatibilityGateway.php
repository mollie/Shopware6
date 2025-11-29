<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Fakes;

use Kiener\MolliePayments\Compatibility\Gateway\CompatibilityGatewayInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class FakeCompatibilityGateway implements CompatibilityGatewayInterface
{
    public function getSalesChannelID(SalesChannelContext $context): string
    {
        return '';
    }

    public function getSalesChannelContext(string $salesChannelID, ?string $domainID, string $token): SalesChannelContext
    {
    }

    public function getDomainId(SalesChannelContext $context): ?string
    {
        return '';
    }

    public function getLineItemPromotionType(): string
    {
        return LineItem::PROMOTION_LINE_ITEM_TYPE;
    }

    public function getChargebackOrderTransactionState(): string
    {
        return 'chargeback';
    }

    public function getChargebackOrderTransactionAction(): string
    {
        return 'chargeback';
    }
}
