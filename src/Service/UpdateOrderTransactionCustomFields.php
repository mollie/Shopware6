<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Struct\OrderTransaction\OrderTransactionAttributes;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class UpdateOrderTransactionCustomFields
{
    /**
     * @var EntityRepository
     */
    private $repoTransactions;

    /**
     * @param EntityRepository $repoTransactions
     */
    public function __construct($repoTransactions)
    {
        $this->repoTransactions = $repoTransactions;
    }

    public function updateOrderTransaction(string $shopwareOrderTransactionId, OrderTransactionAttributes $struct, Context $context): void
    {
        $data = [
            'id' => $shopwareOrderTransactionId,
            'customFields' => [
                CustomFieldsInterface::MOLLIE_KEY => $struct->toArray(),
            ],
        ];

        $this->repoTransactions->update([$data], $context);
    }
}
