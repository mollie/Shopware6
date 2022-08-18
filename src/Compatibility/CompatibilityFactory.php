<?php

namespace Kiener\MolliePayments\Compatibility;

use Kiener\MolliePayments\Compatibility\Gateway\CompatibilityGateway;
use Kiener\MolliePayments\Compatibility\Gateway\CompatibilityGatewayInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
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
     * @var SalesChannelContextPersister
     */
    private $salesChannelContextPersister;

    /**
     * @param string $shopwareVersion
     * @param SalesChannelContextServiceInterface $salesChannelContextService
     * @param SalesChannelContextPersister        $salesChannelContextPersister
     */
    public function __construct(string $shopwareVersion, SalesChannelContextServiceInterface $salesChannelContextService, SalesChannelContextPersister $salesChannelContextPersister)
    {
        $this->shopwareVersion = $shopwareVersion;
        $this->salesChannelContextService = $salesChannelContextService;
        $this->salesChannelContextPersister = $salesChannelContextPersister;
    }

    /**
     * @return CompatibilityGatewayInterface
     */
    public function createGateway(): CompatibilityGatewayInterface
    {
        return new CompatibilityGateway(
            $this->shopwareVersion,
            $this->salesChannelContextService,
            $this->salesChannelContextPersister
        );
    }
}
