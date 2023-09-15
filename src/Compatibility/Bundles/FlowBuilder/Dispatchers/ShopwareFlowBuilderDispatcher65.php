<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Dispatchers;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderDispatcherAdapterInterface;
use Shopware\Core\Content\Flow\Dispatching\FlowDispatcher;
use Shopware\Core\Framework\Event\BusinessEventDispatcher;
use Symfony\Contracts\EventDispatcher\Event;

class ShopwareFlowBuilderDispatcher65 implements FlowBuilderDispatcherAdapterInterface
{
    /**
     * @var FlowDispatcher
     */
    private $dispatcher;


    /**
     * @param FlowDispatcher $dispatcher
     */
    public function __construct($dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param Event $event
     * @return void
     */
    public function dispatch($event)
    {
        $this->dispatcher->dispatch($event);
    }
}
