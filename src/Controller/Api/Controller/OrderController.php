<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\Controller;

use Kiener\MolliePayments\Controller\Api\Order\CancelLineController;
use Kiener\MolliePayments\Controller\Api\Order\OrderControllerBase;
use Kiener\MolliePayments\Controller\Api\Order\ShippingControllerBase;
use Kiener\MolliePayments\Controller\Api\PluginConfig\ConfigControllerBase;
use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class OrderController extends AbstractController
{
    /**
     * @var RequestBagFactory
     */
    private $requestBagFactory;

    /**
     * @var ConfigControllerBase
     */
    private $baseController;

    /**
     * @var ShippingControllerBase
     */
    private $shippingController;

    /**
     * @var OrderControllerBase
     */
    private $orderController;

    /**
     * @var CancelLineController
     */
    private $cancelLineController;

    /**
     * @var EntityRepository
     */
    private $orderRepository;

    /**
     * @param RequestBagFactory $requestBagFactory
     * @param ConfigControllerBase $baseController
     * @param ShippingControllerBase $shippingController
     * @param OrderControllerBase $orderController
     * @param CancelLineController $cancelLineController
     * @param EntityRepository $orderRepository
     */
    public function __construct(
        RequestBagFactory $requestBagFactory,
        ConfigControllerBase $baseController,
        ShippingControllerBase $shippingController,
        OrderControllerBase $orderController,
        CancelLineController $cancelLineController,
        EntityRepository $orderRepository
    ) {
        $this->baseController = $baseController;
        $this->shippingController = $shippingController;
        $this->requestBagFactory = $requestBagFactory;
        $this->orderController = $orderController;
        $this->cancelLineController = $cancelLineController;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Requires orderId, salesChannelId and mollieOrderId to be set in the request.
     */
    public function getOrderDetails(Request $request, Context $context): JsonResponse
    {
        $orderId = $request->get('orderId');
        $scId = $this->getSalesChannelId($orderId, $context);
        $mollieOrderId = $this->getMollieOrderId($orderId, $context);

        $request->request->set('mollieOrderId', $mollieOrderId);
        $request->request->set('salesChannelId', $scId);

        $config = $this->baseController->getRefundManagerConfig($request, $context);
        $shipping = $this->shippingController->total($this->requestBagFactory->createForShipping($request), $context);
        $payment = $this->orderController->paymentUrl($request, $context);
        $cancelStatus = $this->cancelLineController->statusAction($request, $context);

        $result = [
            'config' => $config->getContent(),
            'shipping' => $shipping->getContent(),
            'payment' => $payment->getContent(),
            'cancelStatus' => $cancelStatus->getContent(),
        ];

        foreach ($result as &$item) {
            if (is_string($item)) {
                $item = json_decode($item);
            }
        }

        return new JsonResponse(array_merge(['success' => true,], $result));
    }

    private function getSalesChannelId(string $orderId, Context $context): string
    {
        $orders = $this->orderRepository->search(new Criteria([$orderId]), $context);

        if ($orders->count() === 0) {
            throw new \RuntimeException('Order not found');
        }

        $order = $orders->first();

        return $order->getSalesChannelId();
    }

    private function getMollieOrderId(string $orderId, Context $context): string
    {
        $orders = $this->orderRepository->search(new Criteria([$orderId]), $context);

        if ($orders->count() === 0) {
            throw new \RuntimeException('Order not found');
        }
        /**
         * @var OrderEntity $order
         */
        $order = $orders->first();
        $customFields =  $order->getCustomFields();
        return $customFields[CustomFieldsInterface::MOLLIE_KEY]['order_id']??'';
    }
}
