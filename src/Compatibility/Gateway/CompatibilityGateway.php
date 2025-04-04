<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Compatibility\Gateway;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
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

    public function __construct(string $swVersion, SalesChannelContextServiceInterface $contextService)
    {
        $this->swVersion = $swVersion;
        $this->contextService = $contextService;
    }

    public function getSalesChannelID(SalesChannelContext $context): string
    {
        return $context->getSalesChannel()->getId();
    }

    public function getDomainId(SalesChannelContext $context): ?string
    {
        return $context->getDomainId();
    }

    public function getSalesChannelContext(string $salesChannelID, ?string $domainID, string $token): SalesChannelContext
    {
        if ($this->versionGTE('6.4')) {
            $params = new SalesChannelContextServiceParameters($salesChannelID, $token, null, null, $domainID, null, null);

            return $this->contextService->get($params);
        }

        /* @phpstan-ignore-next-line */
        return $this->contextService->get($salesChannelID, $token, null);
    }

    public function getLineItemPromotionType(): string
    {
        if (defined('Shopware\Core\Checkout\Cart\LineItem::PROMOTION_LINE_ITEM_TYPE')) {
            return LineItem::PROMOTION_LINE_ITEM_TYPE;
        }

        return 'promotion';
    }

    public function getChargebackOrderTransactionState(): string
    {
        // In progress state did not exist before 6.2, so set to open instead.
        if (! $this->versionGTE('6.2')) {
            return OrderTransactionStates::STATE_OPEN;
        }

        // Chargeback state did not exist before 6.2.3, so set to in progress instead.
        if (! $this->versionGTE('6.2.3')) {
            return OrderTransactionStates::STATE_IN_PROGRESS;
        }

        if (defined('Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates::STATE_CHARGEBACK')) {
            return OrderTransactionStates::STATE_CHARGEBACK;
        }

        // Chargeback constant did not exist until 6.4.4, but the state exists since 6.2.3,
        // so return it as string instead.
        return 'chargeback';
    }

    private function versionGTE(string $version): bool
    {
        return version_compare($this->swVersion, $version, '>=');
    }
}
