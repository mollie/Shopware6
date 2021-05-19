<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;


use Kiener\MolliePayments\Exception\InvalidMollieOrderException;
use Kiener\MolliePayments\Struct\MollieOrderCustomFieldsStruct;
use Mollie\Api\Resources\Order as MollieOrder;
use Mollie\Api\Resources\OrderLine;
use Monolog\Logger;
use Shopware\Core\Checkout\Order\OrderEntity as ShopwareOrder;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class UpdateOrderCustomFields
{
    /**
     * @var LoggerService
     */
    private LoggerService $loggerService;
    /**
     * @var EntityRepositoryInterface
     */
    private EntityRepositoryInterface $orderRepository;
    /**
     * @var EntityRepositoryInterface
     */
    private EntityRepositoryInterface $orderLineRepository;

    public function __construct(EntityRepositoryInterface $orderRepository, EntityRepositoryInterface $orderLineRepository, LoggerService $loggerService)
    {

        $this->orderRepository = $orderRepository;
        $this->loggerService = $loggerService;
        $this->orderLineRepository = $orderLineRepository;
    }

    public function updateOrder(string $shopwareOrderId, MollieOrderCustomFieldsStruct $struct, SalesChannelContext $salesChannelContext): void
    {
        $data = [
            'id' => $shopwareOrderId,
            'customFields' => $struct->getMollieCustomFields()
        ];

        $this->orderRepository->update($data, $salesChannelContext->getContext());
    }
}
