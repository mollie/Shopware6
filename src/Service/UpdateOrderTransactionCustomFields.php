<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;


use Kiener\MolliePayments\Struct\MollieOrderTransactionCustomFieldsStruct;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class UpdateOrderTransactionCustomFields
{
    /**
     * @var EntityRepositoryInterface
     */
    private $orderTransactionRepository;

    public function __construct(EntityRepositoryInterface $orderTransactionRepository)
    {

        $this->orderTransactionRepository = $orderTransactionRepository;
    }

    public function updateOrderTransaction(string $shopwareOrderTransactionId, MollieOrderTransactionCustomFieldsStruct $struct, SalesChannelContext $salesChannelContext): void
    {
        $data = [
            'id' => $shopwareOrderTransactionId,
            'customFields' => $struct->getMollieCustomFields()
        ];

        $this->orderTransactionRepository->update([$data], $salesChannelContext->getContext());
    }
}
