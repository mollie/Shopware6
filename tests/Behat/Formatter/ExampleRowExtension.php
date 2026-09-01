<?php

declare(strict_types=1);

namespace Mollie\Shopware\Behat\Formatter;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Vanare\BehatCucumberJsonFormatter\Extension;

/**
 * Registers ExampleRowFormatter in place of the Cucumber formatter it extends. Everything
 * else - the config keys, the output printer, the `--out` handling - stays the vendor's.
 */
final class ExampleRowExtension extends Extension
{
    public function load(ContainerBuilder $container, array $config): void
    {
        parent::load($container, $config);

        $container->getDefinition('json.formatter')->setClass(ExampleRowFormatter::class);
    }
}
