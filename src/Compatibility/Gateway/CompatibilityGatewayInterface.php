<?php

namespace Kiener\MolliePayments\Compatibility\Gateway;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface CompatibilityGatewayInterface
{
    /**
     * @param SalesChannelContext $context
     * @return string
     */
    public function getSalesChannelID(SalesChannelContext $context): string;

    /**
     * @param string $salesChannelID
     * @param string $token
     * @return SalesChannelContext
     */
    public function getSalesChannelContext(string $salesChannelID, string $token): SalesChannelContext;

    /**
     * @return string
     */
    public function getLineItemPromotionType(): string;


    public function getChargebackOrderTransactionState(): string;
}
