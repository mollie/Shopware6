<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\StatusUpdate;

use Mollie\Shopware\Component\StatusUpdate\UpdateStatusScheduledTask;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateStatusScheduledTask::class)]
final class UpdateStatusScheduledTaskTest extends TestCase
{
    /**
     * Without this, a single exception put the task into the failed state, which Shopware's
     * TaskScheduler never re-queues - the automatic status update stopped for good and only a
     * manual database change brought it back.
     */
    public function testAFailingRunKeepsTheTaskScheduled(): void
    {
        $this->assertTrue(UpdateStatusScheduledTask::shouldRescheduleOnFailure());
    }

    public function testTheTaskRunsEveryMinute(): void
    {
        $this->assertSame(60, UpdateStatusScheduledTask::getDefaultInterval());
    }
}
