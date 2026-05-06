<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\ScheduledTask;

use Mollie\Shopware\Component\Subscription\PriceDrift\PriceDriftDetector;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: SubscriptionPriceUpdateTask::class)]
final class SubscriptionPriceUpdateTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection<ScheduledTaskEntity>> $scheduledTaskRepository
     */
    public function __construct(
        #[Autowire(service: 'scheduled_task.repository')]
        EntityRepository $scheduledTaskRepository,
        private readonly PriceDriftDetector $priceDriftDetector,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($scheduledTaskRepository, $this->logger);
    }

    public function run(): void
    {
        $context = new Context(new SystemSource());

        try {
            $count = $this->priceDriftDetector->detect($context);
            $this->logger->debug(sprintf('%d subscription price change notices dispatched', $count));
        } catch (\Throwable $exception) {
            $this->logger->error('Subscription price update scheduled task (detect) failed: ' . $exception->getMessage());
        }
    }

    /**
     * @return iterable<class-string>
     */
    public static function getHandledMessages(): iterable
    {
        return [SubscriptionPriceUpdateTask::class];
    }
}
