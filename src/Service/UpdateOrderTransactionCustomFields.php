<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Struct\OrderTransaction\OrderTransactionAttributes;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class UpdateOrderTransactionCustomFields
{
    /**
     * @var EntityRepositoryInterface
     */
    private $repoTransactions;


    /**
     * @param EntityRepositoryInterface $repoTransactions
     */
    public function __construct(EntityRepositoryInterface $repoTransactions)
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
