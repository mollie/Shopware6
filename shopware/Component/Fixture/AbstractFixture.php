<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Fixture;

use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

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
