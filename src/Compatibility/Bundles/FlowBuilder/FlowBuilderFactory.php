<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Dispatchers\DummyFlowBuilderDispatcher;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Dispatchers\ShopwareFlowBuilderDispatcher;
use Kiener\MolliePayments\Compatibility\VersionCompare;


class FlowBuilderFactory implements FlowBuilderFactoryInterface
{

    /**
     *
     */
    public const FLOW_BUILDER_MIN_VERSION = '6.4.6.0';

    /**
     * @var VersionCompare
     */
    private $versionCompare;

    /**
     * @var \Shopware\Core\Framework\Event\BusinessEventDispatcher|null
     */
    private $businessEventDispatcher;


    /**
     * @param string $shopwareVersion
     * @param \Shopware\Core\Framework\Event\BusinessEventDispatcher $businessEventDispatcher
     */
    public function __construct(string $shopwareVersion, $businessEventDispatcher)
    {
        $this->versionCompare = new VersionCompare($shopwareVersion);
        $this->businessEventDispatcher = $businessEventDispatcher;
    }


    /**
     * @return FlowBuilderDispatcherAdapterInterface
     * @throws \Exception
     */
    public function createDispatcher(): FlowBuilderDispatcherAdapterInterface
    {
        if ($this->versionCompare->lt(self::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyFlowBuilderDispatcher();
        }

        if ($this->businessEventDispatcher === null) {
            throw new \Exception('Required Flow Builder Event Dispatcher not existing in this Shopware Version!');
        }

        return new ShopwareFlowBuilderDispatcher($this->businessEventDispatcher);
    }

}
