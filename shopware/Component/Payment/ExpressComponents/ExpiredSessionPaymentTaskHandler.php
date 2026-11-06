<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: ExpiredSessionPaymentScheduledTask::class)]
final class ExpiredSessionPaymentTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection<ScheduledTaskEntity>> $scheduledTaskRepository
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        #[Autowire(service: 'scheduled_task.repository')]
        EntityRepository $scheduledTaskRepository,
        #[Autowire(service: SessionPaymentRepository::class)]
        private SessionPaymentRepositoryInterface $sessionPaymentRepository,
        #[Autowire(service: 'sales_channel.repository')]
        private EntityRepository $salesChannelRepository,
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $context = new Context(new SystemSource());
        if (! $this->isEnabledInAnySalesChannel($context)) {
            return;
        }

        $deleted = $this->sessionPaymentRepository->deleteExpired(new \DateTimeImmutable(), $context);

        $this->logger->debug('Expired express components sessions deleted', ['deleted' => $deleted]);
    }

    /**
     * The sessions are not stored per sales channel, so they are cleaned up as soon as one sales
     * channel offers express components.
     */
    private function isEnabledInAnySalesChannel(Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));

        /** @var array<string> $salesChannelIds */
        $salesChannelIds = $this->salesChannelRepository->searchIds($criteria, $context)->getIds();
        foreach ($salesChannelIds as $salesChannelId) {
            if ($this->settingsService->getExpressComponentsSettings($salesChannelId)->isEnabled()) {
                return true;
            }
        }

        return false;
    }
}
