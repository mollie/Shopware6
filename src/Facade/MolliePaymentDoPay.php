<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Exception\MollieOrderCouldNotBeCancelledException;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\MollieApi\Order as ApiOrderService;
use Kiener\MolliePayments\Struct\MollieOrderCustomFieldsStruct;
use Monolog\Logger;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class MolliePaymentDoPay
{
    /**
     * @var ApiOrderService
     */
    private $apiOrderService;
    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;
    /**
     * @var LoggerService
     */
    private $logger;

    /**
     * @param ApiOrderService $apiOrderService
     */
    public function __construct(ApiOrderService $apiOrderService, EntityRepositoryInterface $orderRepository, LoggerService $logger)
    {

        $this->apiOrderService = $apiOrderService;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    public function getPaymentUrl(string $paymentMethod, AsyncPaymentTransactionStruct $transactionStruct, SalesChannelContext $salesChannelContext): string
    {
        $order = $this->getOrder($transactionStruct->getOrder()->getId(), $salesChannelContext);
        $customFields = new MollieOrderCustomFieldsStruct($order->getCustomFields());

        $mollieOrderId = $customFields->getMollieOrderId();
        if (!empty($mollieOrderId)) {
            // cancel previous payment at mollie
            try {
                $this->apiOrderService->cancelOrder($mollieOrderId, $salesChannelContext);
            } catch (MollieOrderCouldNotBeCancelledException $e) {
                // we do nothing here. This should not happen, but if it happens it will not harm
                $this->logger->addEntry(
                    sprintf('Tried to cancel mollie order (%s). Api call resulted in error', $mollieOrderId),
                    $salesChannelContext->getContext()
                );
            }

            $customFields->setMollieOrderId(null);
        }


    }

    /**
     * returns an order with all necessary associations to create a mollie api order
     *
     * @param string $orderId
     * @return OrderEntity
     */
    private function getOrder(string $orderId, SalesChannelContext $salesChannelContext): OrderEntity
    {
        $context = $salesChannelContext->getContext();
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('currency');
        $criteria->addAssociation('addresses');
        $criteria->addAssociation('language');
        $criteria->addAssociation('language.locale');
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('lineItems.product');
        $criteria->addAssociation('lineItems.product.media');
        $criteria->addAssociation('deliveries');
        $criteria->addAssociation('deliveries.shippingOrderAddress');
        $criteria->addAssociation('transactions');
        $criteria->addAssociation('transactions.paymentMethod');

        /** @var OrderEntity $order */
        $order = $this->orderRepository->search($criteria, $context)->first();

        if ($order instanceof OrderEntity) {
            return $order;
        }

        $this->logger->addEntry(
            sprintf('Could not find an order with id %s. Payment failed', $orderId),
            $context,
            null,
            null,
            Logger::CRITICAL
        );

        throw new OrderNotFoundException($orderId);
    }
}
