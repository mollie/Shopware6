<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\PluginConfig\Services;

use Kiener\MolliePayments\Components\RefundManager\RefundManagerInterface;
use Kiener\MolliePayments\Controller\Api\PluginConfig\ConfigControllerResponse;
use Kiener\MolliePayments\Controller\Api\PluginConfig\Exceptions\EmptyOrderIdProvidedConfigException;
use Kiener\MolliePayments\Controller\Api\PluginConfig\Exceptions\MetaDataNotFoundInRefundConfigException;
use Kiener\MolliePayments\Controller\Api\PluginConfig\Exceptions\MollieRefundConfigException;
use Kiener\MolliePayments\Controller\Api\PluginConfig\Structs\Collections\OrderLineItemStructCollection;
use Kiener\MolliePayments\Controller\Api\PluginConfig\Structs\OrderLineItemStruct;
use Kiener\MolliePayments\Service\MollieApi\Order as MollieOrderService;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Shopware\Core\Framework\Context;

class MollieRefundConfigService
{
    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var RefundManagerInterface
     */
    private $refundManager;

    /**
     * @var MollieOrderService
     */
    private $mollieOrderService;

    public function __construct(
        OrderService $orderService,
        RefundManagerInterface $refundManager,
        MollieOrderService $mollieOrderService
    ) {
        $this->orderService = $orderService;
        $this->refundManager = $refundManager;
        $this->mollieOrderService = $mollieOrderService;
    }

    /**
     * @throws MollieRefundConfigException
     */
    public function createConfigControllerResponse(string $orderId, MollieSettingStruct $config, string $salesChannelId, Context $context): ConfigControllerResponse
    {
        if (empty($orderId)) {
            throw EmptyOrderIdProvidedConfigException::create();
        }

        try {
            $order = $this->orderService->getOrder($orderId, $context);
            $orderAttributes = new OrderAttributes($order);
            $refundData = $this->refundManager->getData($order, $context);
            $refunds = $refundData->getRefunds();
            $mollieOrder = $this->mollieOrderService->getMollieOrder($orderAttributes->getMollieOrderId(), $salesChannelId);

            $structs = [];

            foreach ($mollieOrder->lines() as $line) {
                $structs[] = OrderLineItemStruct::createWithId($line->metadata->orderLineItemId)
                    ->setRefundableQuantity($line->refundableQuantity)
                    ->setOrderedQuantity($line->quantity)
                ;
            }

            $structs = OrderLineItemStructCollection::create(...$structs);

            foreach ($refunds as $refund) {
                if (! isset($refund['metadata'], $refund['metadata']->composition)) {
                    throw MetaDataNotFoundInRefundConfigException::create();
                }

                $composition = $refund['metadata']->composition;
                foreach ($composition as $item) {
                    $structs->getById($item['swLineId'])
                        ->setHasPendingRefund($refund['isPending'])
                        ->setRefundedCount($item['quantity'])
                    ;
                }
            }

            return $this->createResponse($structs, $config);
        } catch (MollieRefundConfigException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw MollieRefundConfigException::fromException($exception);
        }
    }

    public function createResponse(OrderLineItemStructCollection $lineItems, MollieSettingStruct $config): ConfigControllerResponse
    {
        $hasRefundableItems = false;

        foreach ($lineItems as $lineItem) {
            // when the line item has a pending refund, the merchant
            // needs to be able to open the refund manager to cancel the refund
            if ($lineItem->hasPendingRefund()) {
                $hasRefundableItems = true;
                break;
            }

            // only items that have not been fully refunded can be refunded
            if ($lineItem->getRefundedCount() < $lineItem->getOrderedQuantity()) {
                $hasRefundableItems = true;
                break;
            }

            if ($lineItem->getRefundableQuantity() > 0) {
                $hasRefundableItems = true;
                break;
            }
        }

        return ConfigControllerResponse::createFromValues(
            $config->isRefundManagerEnabled() && $hasRefundableItems,
            $config->isRefundManagerAutoStockReset(),
            $config->isRefundManagerVerifyRefund(),
            $config->isRefundManagerShowInstructions()
        );
    }
}
