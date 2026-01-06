<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Fixture;

use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('mollie.fixture')]
abstract class AbstractFixture
{

    abstract public function getGroup(): FixtureGroup;

    abstract public function install(Context $context): void;

    abstract public function uninstall(Context $context): void;

    public function getPriority(): int
    {
        return 0;
    }
}
