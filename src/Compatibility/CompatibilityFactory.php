<?php

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


    /**
     * @param string $shopwareVersion
     * @param SalesChannelContextServiceInterface $salesChannelContextService
     */
    public function __construct(string $shopwareVersion, SalesChannelContextServiceInterface $salesChannelContextService)
    {
        $this->shopwareVersion = $shopwareVersion;
        $this->salesChannelContextService = $salesChannelContextService;
    }

    /**
     * @return CompatibilityGatewayInterface
     */
    public function createGateway(): CompatibilityGatewayInterface
    {
        return new CompatibilityGateway(
            $this->shopwareVersion,
            $this->salesChannelContextService
        );
    }
}
