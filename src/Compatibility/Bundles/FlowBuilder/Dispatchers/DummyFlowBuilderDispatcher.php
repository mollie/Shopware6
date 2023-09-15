<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Dispatchers;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderDispatcherAdapterInterface;

class DummyFlowBuilderDispatcher implements FlowBuilderDispatcherAdapterInterface
{
    /**
     * @param mixed $event
     * @return void
     */
    public function dispatch($event)
    {
        # do nothing, it's just a dummy one
        # for older Shopware versions
    }
}
