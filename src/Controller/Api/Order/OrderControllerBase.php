<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\Order;

use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\OrderService;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderControllerBase extends AbstractController
{
    /** @var OrderService */
    private $orderService;

    /** @var Order */
    private $mollieOrderService;

    public function __construct(OrderService $orderService, Order $mollieOrderService)
    {
        $this->orderService = $orderService;
        $this->mollieOrderService = $mollieOrderService;
    }

    public function paymentUrl(Request $request, Context $context): JsonResponse
    {
        $orderId = $request->get('orderId');

        return $this->paymentUrlResponse($orderId, $context);
    }

    public function paymentUrlLegacy(Request $request, Context $context): JsonResponse
    {
        $orderId = $request->get('orderId');

        return $this->paymentUrlResponse($orderId, $context);
    }

    private function paymentUrlResponse(string $orderId, Context $context): JsonResponse
    {
        $order = $this->orderService->getOrder($orderId, $context);

        $customFields = $order->getCustomFields();

        $mollieOrderId = ($customFields !== null && isset($customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::ORDER_KEY])) ? $customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::ORDER_KEY] : null;

        if (is_null($mollieOrderId)) {
            return $this->json([], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'url' => $this->mollieOrderService->getPaymentUrl($mollieOrderId, $order->getSalesChannelId()),
        ]);
    }
}
