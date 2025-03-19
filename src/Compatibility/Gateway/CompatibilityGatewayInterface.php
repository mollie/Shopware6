<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Compatibility\Gateway;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface CompatibilityGatewayInterface
{
    public function getSalesChannelID(SalesChannelContext $context): string;

    public function getSalesChannelContext(string $salesChannelID, ?string $domainID, string $token): SalesChannelContext;

    public function getDomainId(SalesChannelContext $context): ?string;

    public function getLineItemPromotionType(): string;

    public function getChargebackOrderTransactionState(): string;
}
