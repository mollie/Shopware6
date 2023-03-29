<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Repository\OrderTransaction\OrderTransactionRepositoryInterface;
use Kiener\MolliePayments\Struct\OrderTransaction\OrderTransactionAttributes;
use Shopware\Core\Framework\Context;

class UpdateOrderTransactionCustomFields
{
    /**
     * @var OrderTransactionRepositoryInterface
     */
    private $repoTransactions;


    /**
     * @param OrderTransactionRepositoryInterface $repoTransactions
     */
    public function __construct(OrderTransactionRepositoryInterface $repoTransactions)
    {
        $this->repoTransactions = $repoTransactions;
    }

    /**
     * @param string $shopwareOrderTransactionId
     * @param OrderTransactionAttributes $struct
     * @param Context $context
     * @return void
     */
    public function updateOrderTransaction(string $shopwareOrderTransactionId, OrderTransactionAttributes $struct, Context $context): void
    {
        $data = [
            'id' => $shopwareOrderTransactionId,
            'customFields' => [
                'mollie_payments' => $struct->toArray(),
            ]
        ];

        $this->repoTransactions->update([$data], $context);
    }
}
