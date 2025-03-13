<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Traits;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

trait MockTrait
{
    protected function createDummyMock(string $originalClassName, TestCase $testCase): MockObject
    {
        return $testCase->getMockBuilder($originalClassName)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes()
            ->getMock()
        ;
    }
}
