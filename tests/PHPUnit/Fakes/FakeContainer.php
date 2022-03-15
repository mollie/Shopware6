<?php

namespace MolliePayments\Tests\Fakes;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class FakeContainer implements ContainerInterface
{
    public function get(string $id)
    {
        // TODO: Implement get() method.
    }

    public function has(string $id)
    {
        // TODO: Implement has() method.
    }


}