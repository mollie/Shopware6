<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\StatusUpdate;

use Kiener\MolliePayments\Controller\Storefront\Webhook\NotificationFacade;
use Mollie\Shopware\Repository\OrderTransactionRepositoryInterface;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;

final class UpdateStatusAction
{
    private OrderTransactionRepositoryInterface $transactionRepository;
    private NotificationFacade $notification;

    public function __construct(OrderTransactionRepositoryInterface $transactionRepository, NotificationFacade $notification)
    {
        $this->transactionRepository = $transactionRepository;
        $this->notification = $notification;
    }

    public function execute(): UpdateStatusResult
    {
        $result = new UpdateStatusResult();
        $context = new Context(new SystemSource());

        $transactions = $this->transactionRepository->findOpenTransactions($context);

        if ($transactions->getTotal() === 0) {
            return $result;
        }
        $transactionIds = $transactions->getIds();
        /** @var string $transactionId */
        foreach ($transactionIds as $transactionId) {
            $result->addUpdateId($transactionId);
            $this->notification->onNotify($transactionId, $context);
        }

        return $result;
    }
}
