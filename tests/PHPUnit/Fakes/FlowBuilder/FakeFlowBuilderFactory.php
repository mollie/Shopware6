<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Fakes\FlowBuilder;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderDispatcherAdapterInterface;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactoryInterface;

class FakeFlowBuilderFactory implements FlowBuilderFactoryInterface
{
    /**
     * @var FlowBuilderDispatcherAdapterInterface
     */
    private $dispatcher;

    public function __construct(FlowBuilderDispatcherAdapterInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function createDispatcher(): FlowBuilderDispatcherAdapterInterface
    {
        return $this->dispatcher;
    }
}
