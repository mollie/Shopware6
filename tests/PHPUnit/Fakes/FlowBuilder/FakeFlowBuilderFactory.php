<?php

namespace MolliePayments\Tests\Fakes\FlowBuilder;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderDispatcherAdapterInterface;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactoryInterface;

class FakeFlowBuilderFactory implements FlowBuilderFactoryInterface
{

    /**
     * @var FlowBuilderDispatcherAdapterInterface
     */
    private $dispatcher;


    /**
     * @param FlowBuilderDispatcherAdapterInterface $dispatcher
     */
    public function __construct(FlowBuilderDispatcherAdapterInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @return FlowBuilderDispatcherAdapterInterface
     */
    public function createDispatcher(): FlowBuilderDispatcherAdapterInterface
    {
        return $this->dispatcher;
    }

}
