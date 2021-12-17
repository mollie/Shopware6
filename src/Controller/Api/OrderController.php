<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api;

use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\OrderService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class OrderController extends AbstractController
{
    /** @var OrderService */
    private $orderService;

    /** @var Order */
    private $mollieOrderService;

    public function __construct(
        OrderService $orderService,
        Order $mollieOrderService
    )
    {
        $this->orderService = $orderService;
        $this->mollieOrderService = $mollieOrderService;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/mollie/order/payment-url",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.order.payment-url", methods={"POST"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function paymentUrl(Request $request, Context $context): JsonResponse
    {
        $orderId = $request->get('orderId');

        return $this->paymentUrlResponse($orderId, $context);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/v{version}/_action/mollie/order/payment-url",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.order.payment-url.legacy", methods={"POST"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function paymentUrlLegacy(Request $request, Context $context): JsonResponse
    {
        $orderId = $request->get('orderId');

        return $this->paymentUrlResponse($orderId, $context);
    }

    private function paymentUrlResponse(string $orderId, Context $context): JsonResponse
    {
        $order = $this->orderService->getOrder($orderId, $context);

        $mollieOrderId = $order->getCustomFields()['mollie_payments']['order_id'] ?? null;

        if (is_null($mollieOrderId)) {
            return $this->json([], 404);
        }

        return new JsonResponse([
            'url' => $this->mollieOrderService->getPaymentUrl($mollieOrderId, $order->getSalesChannelId()),
        ]);
    }
}
