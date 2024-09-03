<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\ScheduledTask\Subscription\RenewalReminder;

#[\Symfony\Component\Messenger\Attribute\AsMessageHandler(handles: RenewalReminderTaskDev::class)]
class RenewalReminderTaskDevHandler extends AbstractRenewalReminderTaskHandler
{
}
