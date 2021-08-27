<?php

namespace Kiener\MolliePayments\Factory;

use Kiener\MolliePayments\Gateway\CompatibilityGatewayInterface;
use Kiener\MolliePayments\Gateway\Shopware\CompatibilityGateway;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;

class CompatibilityGatewayFactory
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
    public function create(): CompatibilityGatewayInterface
    {
        return new CompatibilityGateway(
            $this->shopwareVersion,
            $this->salesChannelContextService
        );
    }

}
