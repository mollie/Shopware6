<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\StatusUpdate;

use Mollie\Shopware\Component\Payment\Route\AbstractWebhookRoute;
use Mollie\Shopware\Component\Payment\Route\WebhookRoute;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Repository\OrderTransactionRepositoryInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class UpdateStatusAction
{
    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private OrderTransactionRepositoryInterface $transactionRepository,
        #[Autowire(service: WebhookRoute::class)]
        private AbstractWebhookRoute $webhookRoute,
        #[Autowire(service: 'sales_channel.repository')]
        private readonly EntityRepository $salesChannelRepository,
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public function execute(): UpdateStatusResult
    {
        $result = new UpdateStatusResult();
        $context = new Context(new SystemSource());

        $salesChannelIds = $this->findActiveSalesChannelIds($context);
        $enabledSalesChannels = 0;

        /** @var string $salesChannelId */
        foreach ($salesChannelIds as $salesChannelId) {
            if (! $this->settingsService->getPaymentSettings($salesChannelId)->isAutomaticStatusUpdate()) {
                continue;
            }

            ++$enabledSalesChannels;

            $transactions = $this->transactionRepository->findOpenTransactions($salesChannelId, $context);

            /** @var string $transactionId */
            foreach ($transactions->getIds() as $transactionId) {
                if (! $this->notify($transactionId, $context)) {
                    continue;
                }

                $result->addUpdateId($transactionId);
            }
        }

        if ($enabledSalesChannels === 0) {
            $this->logger->debug('Skipped status update, no active sales channel has the automatic status update enabled');
        }

        return $result;
    }

    /**
     * @return array<string>
     */
    private function findActiveSalesChannelIds(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));

        return $this->salesChannelRepository->searchIds($criteria, $context)->getIds();
    }

    private function notify(string $transactionId, Context $context): bool
    {
        try {
            $this->webhookRoute->notify($transactionId, $context);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to update status for transaction', [
                'transactionId' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
