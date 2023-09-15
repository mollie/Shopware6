<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Dispatchers\DummyFlowBuilderDispatcher;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Dispatchers\ShopwareFlowBuilderDispatcher;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Dispatchers\ShopwareFlowBuilderDispatcher65;
use Kiener\MolliePayments\Compatibility\VersionCompare;
use Shopware\Core\Content\Flow\Dispatching\FlowDispatcher;

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
     * @var null|\Shopware\Core\Framework\Event\BusinessEventDispatcher
     */
    /** @phpstan-ignore-next-line */
    private $businessEventDispatcher;

    /**
     * @var FlowDispatcher
     */
    private $flowDispatcher;


    /**
     * @param string $shopwareVersion
     * @param \Shopware\Core\Framework\Event\BusinessEventDispatcher $businessEventDispatcher
     * @param FlowDispatcher $flowDispatcher
     */
    /** @phpstan-ignore-next-line */
    public function __construct(string $shopwareVersion, $businessEventDispatcher, $flowDispatcher)
    {
        $this->versionCompare = new VersionCompare($shopwareVersion);
        $this->businessEventDispatcher = $businessEventDispatcher;
        $this->flowDispatcher = $flowDispatcher;
    }


    /**
     * @throws \Exception
     * @return FlowBuilderDispatcherAdapterInterface
     */
    public function createDispatcher(): FlowBuilderDispatcherAdapterInterface
    {
        if ($this->versionCompare->gte('6.5.0.0')) {
            return new ShopwareFlowBuilderDispatcher65($this->flowDispatcher);
        }

        if ($this->versionCompare->lt(self::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyFlowBuilderDispatcher();
        }

        if ($this->businessEventDispatcher === null) {
            throw new \Exception('Required Flow Builder Event Dispatcher not existing in this Shopware Version!');
        }

        return new ShopwareFlowBuilderDispatcher($this->businessEventDispatcher);
    }
}
