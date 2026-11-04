<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: ExpiredSessionPaymentScheduledTask::class)]
final class ExpiredSessionPaymentTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection<ScheduledTaskEntity>> $scheduledTaskRepository
     */
    public function __construct(
        #[Autowire(service: 'scheduled_task.repository')]
        EntityRepository $scheduledTaskRepository,
        #[Autowire(service: SessionPaymentRepository::class)]
        private SessionPaymentRepositoryInterface $sessionPaymentRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $deleted = $this->sessionPaymentRepository->deleteExpired(new \DateTimeImmutable(), new Context(new SystemSource()));

        $this->logger->debug('Expired express components sessions deleted', ['deleted' => $deleted]);
    }
}
