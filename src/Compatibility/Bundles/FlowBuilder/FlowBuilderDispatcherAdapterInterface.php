<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder;

interface FlowBuilderDispatcherAdapterInterface
{
    /**
     * @param mixed $event
     *
     * @return mixed
     */
    public function dispatch($event);
}
