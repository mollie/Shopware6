<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder;

interface FlowBuilderDispatcherAdapterInterface
{
    public function dispatch($event);
}
