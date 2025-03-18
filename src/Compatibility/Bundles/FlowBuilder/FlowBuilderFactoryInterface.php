<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder;

interface FlowBuilderFactoryInterface
{
    public function createDispatcher(): FlowBuilderDispatcherAdapterInterface;
}
