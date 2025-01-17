<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder;

interface FlowBuilderFactoryInterface
{
    /**
     * @return FlowBuilderDispatcherAdapterInterface
     */
    public function createDispatcher(): FlowBuilderDispatcherAdapterInterface;
}
