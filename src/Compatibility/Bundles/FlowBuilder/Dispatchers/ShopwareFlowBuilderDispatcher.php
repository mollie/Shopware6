<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Dispatchers;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderDispatcherAdapterInterface;
use Shopware\Core\Framework\Event\BusinessEventDispatcher;


class ShopwareFlowBuilderDispatcher implements FlowBuilderDispatcherAdapterInterface
{

    /**
     * @var BusinessEventDispatcher
     */
    private $dispatcher;


    /**
     * @param BusinessEventDispatcher $dispatcher
     */
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
