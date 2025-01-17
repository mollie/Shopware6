<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Dispatchers;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderDispatcherAdapterInterface;
use Shopware\Core\Framework\Event\BusinessEventDispatcher;

class ShopwareFlowBuilderDispatcher implements FlowBuilderDispatcherAdapterInterface
{
    /**
     * @var BusinessEventDispatcher
     */
    /** @phpstan-ignore-next-line */
    private $dispatcher;


    /**
     * @param BusinessEventDispatcher $dispatcher
     */
    /** @phpstan-ignore-next-line */
    public function __construct($dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param mixed $event
     * @return void
     */
    public function dispatch($event)
    {
        $this->dispatcher->dispatch($event);
    }
}
