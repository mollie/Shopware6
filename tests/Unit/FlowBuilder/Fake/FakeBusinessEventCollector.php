<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\FlowBuilder\Fake;

use Shopware\Core\Framework\Event\BusinessEventCollector;

/**
 * The real collector only needs its DBAL connection for the app events it reads in collect().
 * define() - the only method the plugin calls - works without any of the constructor arguments,
 * so it stays the real implementation here.
 */
final class FakeBusinessEventCollector extends BusinessEventCollector
{
    public function __construct()
    {
    }
}
