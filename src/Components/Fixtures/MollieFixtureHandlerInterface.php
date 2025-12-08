<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Fixtures;

interface MollieFixtureHandlerInterface
{
    public function install(): void;

    public function uninstall(): void;
}
