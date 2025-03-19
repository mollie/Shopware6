<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Fakes\FlowBuilder;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderDispatcherAdapterInterface;

class FakeFlowBuilderDispatcher implements FlowBuilderDispatcherAdapterInterface
{
    private $dispatchedEvent;

    public function getDispatchedEvent()
    {
        return $this->dispatchedEvent;
    }

    /**
     * @return mixed|void
     */
    public function dispatch($event)
    {
        $this->dispatchedEvent = $event;
    }
}
