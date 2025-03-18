<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\ScheduledTask\OrderStatus;

use Kiener\MolliePayments\Components\OrderExpiration\ExpireAction;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

#[\Symfony\Component\Messenger\Attribute\AsMessageHandler(handles: ExpireOrderTask::class)]
class ExpireOrderTaskHandler extends ScheduledTaskHandler
{
    private ExpireAction $expireAction;

    /**
     * @param EntityRepository $scheduledTaskRepository
     */
    public function __construct(
        $scheduledTaskRepository,
        ExpireAction $expireAction,
        LoggerInterface $logger
    ) {
        /* @phpstan-ignore-next-line */
        parent::__construct($scheduledTaskRepository, $logger);
        $this->expireAction = $expireAction;
    }

    public function run(): void
    {
        $context = new Context(new SystemSource());
        $this->expireAction->expireOrders($context);
    }

    /**
     * @return iterable<mixed>
     */
    public static function getHandledMessages(): iterable
    {
        return [
            ExpireOrderTask::class,
        ];
    }
}
