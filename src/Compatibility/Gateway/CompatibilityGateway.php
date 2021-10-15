<?php

namespace Kiener\MolliePayments\Compatibility\Gateway;

use Kiener\MolliePayments\Compatibility\Gateway\CompatibilityGatewayInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelContext;


class CompatibilityGateway implements CompatibilityGatewayInterface
{

    /**
     * @var string
     */
    private $swVersion;

    /**
     * @var SalesChannelContextServiceInterface
     */
    private $contextService;


    /**
     * @param string $swVersion
     * @param SalesChannelContextServiceInterface $contextService
     */
    public function __construct(string $swVersion, SalesChannelContextServiceInterface $contextService)
    {
        $this->swVersion = $swVersion;
        $this->contextService = $contextService;
    }


    /**
     * @param SalesChannelContext $context
     * @return string
     */
    public function getSalesChannelID(SalesChannelContext $context): string
    {
        return $context->getSalesChannel()->getId();
    }

    /**
     * @param string $salesChannelID
     * @param string $token
     * @return SalesChannelContext
     */
    public function getSalesChannelContext(string $salesChannelID, string $token): SalesChannelContext
    {
        if ($this->versionGTE('6.4')) {
            $params = new SalesChannelContextServiceParameters($salesChannelID, $token);
            return $this->contextService->get($params);
        }

        /* @phpstan-ignore-next-line */
        $context = $this->contextService->get($salesChannelID, $token, null);

        return $context;
    }

    /**
     * @return string
     */
    public function getLineItemPromotionType(): string
    {
        if (defined('Shopware\Core\Checkout\Cart\LineItem::PROMOTION_LINE_ITEM_TYPE')) {
            return LineItem::PROMOTION_LINE_ITEM_TYPE;
        }

        return 'promotion';
    }

    /**
     * @param string $version
     * @return bool
     */
    private function versionGTE(string $version): bool
    {
        return version_compare($this->swVersion, $version, '>=');
    }

}
