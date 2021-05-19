<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;


use Kiener\MolliePayments\Exception\InvalidMollieOrderException;
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

    public function updateOrder(ShopwareOrder $shopwareOrder, MollieOrder $mollieOrder, string $returnUrl, SalesChannelContext $salesChannelContext): void
    {
        if (empty($mollieOrder->id)) {
            $this->loggerService->addEntry(
                'The given mollie order has no id!',
                $salesChannelContext->getContext(),
                null,
                null,
                Logger::CRITICAL
            );

            throw new InvalidMollieOrderException();
        }

        $data = [
            'id' => $shopwareOrder->getId(),
            'customFields' => [
                'mollie_payments' => [
                    'order_id' => $mollieOrder->id,
                    'transactionReturnUrl' => $returnUrl
                ]
            ]
        ];

        $this->orderRepository->update($data, $salesChannelContext->getContext());

        /** @var OrderLine $orderLine */
        foreach ($mollieOrder->lines() as $orderLine) {
            $shopwareLineItemId = (string)$orderLine->metadata->orderLineItemId ?? '';

            if (empty($shopwareLineItemId)) {
                continue;
            }

            $data = [
                'id' => $shopwareLineItemId,
                'customFields' => [
                    'mollie_payments' => [
                        'order_line_id' => $orderLine->id
                    ]
                ]
            ];

            $this->orderLineRepository->update($data, $salesChannelContext->getContext());
        }
    }
}
