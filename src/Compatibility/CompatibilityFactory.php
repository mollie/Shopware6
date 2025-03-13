<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Compatibility;

use Kiener\MolliePayments\Compatibility\Gateway\CompatibilityGateway;
use Kiener\MolliePayments\Compatibility\Gateway\CompatibilityGatewayInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;

class CompatibilityFactory
{
    /**
     * @var string
     */
    private $shopwareVersion;

    /**
     * @var SalesChannelContextServiceInterface
     */
    private $salesChannelContextService;

    public function __construct(string $shopwareVersion, SalesChannelContextServiceInterface $salesChannelContextService)
    {
        $this->shopwareVersion = $shopwareVersion;
        $this->salesChannelContextService = $salesChannelContextService;
    }

    public function createGateway(): CompatibilityGatewayInterface
    {
        return new CompatibilityGateway(
            $this->shopwareVersion,
            $this->salesChannelContextService
        );
    }
}
