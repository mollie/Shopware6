<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\StatusUpdate;

use Mollie\Shopware\Component\Payment\Route\AbstractWebhookRoute;
use Mollie\Shopware\Component\Payment\Route\WebhookRoute;
use Mollie\Shopware\Repository\OrderTransactionRepositoryInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class UpdateStatusAction
{
    public function __construct(
        private OrderTransactionRepositoryInterface $transactionRepository,
        #[Autowire(service: WebhookRoute::class)]
        private AbstractWebhookRoute $webhookRoute,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public function execute(): UpdateStatusResult
    {
        $result = new UpdateStatusResult();
        $context = new Context(new SystemSource());

        $transactions = $this->transactionRepository->findOpenTransactions($context);

        if ($transactions->getTotal() === 0) {
            return $result;
        }

        /** @var string $transactionId */
        foreach ($transactions->getIds() as $transactionId) {
            try {
                $this->webhookRoute->notify($transactionId, $context);
                $result->addUpdateId($transactionId);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to update status for transaction', [
                    'transactionId' => $transactionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }
}
