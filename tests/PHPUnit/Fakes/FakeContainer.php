<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Fakes;

use Psr\Container\ContainerInterface;

class FakeContainer implements ContainerInterface
{
    public function get(string $id)
    {
        // TODO: Implement get() method.
    }

    public function has(string $id): bool
    {
        // TODO: Implement has() method.
    }
}
