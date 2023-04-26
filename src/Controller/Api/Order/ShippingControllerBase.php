<?php

namespace Kiener\MolliePayments\Controller\Api\Order;

use Exception;
use Kiener\MolliePayments\Facade\MollieShipment;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Resources\Shipment;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ShippingControllerBase extends AbstractController
{
    /**
     * @var MollieShipment
     */
    private $shipmentFacade;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param MollieShipment $shipmentFacade
     * @param LoggerInterface $logger
     */
    public function __construct(MollieShipment $shipmentFacade, LoggerInterface $logger)
    {
        $this->shipmentFacade = $shipmentFacade;
        $this->logger = $logger;
    }

    /**
     * @Route("/api/mollie/ship/order", name="api.mollie.ship.order", methods={"GET"})
     *
     * @param QueryDataBag $query
     * @param Context $context
     * @return JsonResponse
     */
    public function shipOrderApi(QueryDataBag $query, Context $context): JsonResponse
    {
        try {
            $orderNumber = $query->get('number');
            $trackingCarrier = $query->get('trackingCarrier', '');
            $trackingCode = $query->get('trackingCode', '');
            $trackingUrl = $query->get('trackingUrl', '');

            if ($orderNumber === null) {
                throw new \InvalidArgumentException('Missing Argument for Order Number!');
            }

            $shipment = $this->shipmentFacade->shipOrderByOrderNumber(
                $orderNumber,
                $trackingCarrier,
                $trackingCode,
                $trackingUrl,
                $context
            );

            return $this->shipmentToJson($shipment);
        } catch (\Exception $e) {
            $data = [
                'orderNumber' => $orderNumber,
                'trackingCarrier' => $trackingCarrier,
                'trackingCode' => $trackingCode,
                'trackingUrl' => $trackingUrl,
            ];

            return $this->exceptionToJson($e, $data);
        }
    }

    /**
     * @Route("/api/mollie/ship/item", name="api.mollie.ship.item", methods={"GET"})
     *
     * @param QueryDataBag $query
     * @param Context $context
     * @throws \Exception
     * @return JsonResponse
     */
    public function shipItemApi(QueryDataBag $query, Context $context): JsonResponse
    {
        try {
            $orderNumber = $query->get('order');
            $itemIdentifier = $query->get('item');
            $quantity = $query->getInt('quantity');
            $trackingCarrier = $query->get('trackingCarrier', '');
            $trackingCode = $query->get('trackingCode', '');
            $trackingUrl = $query->get('trackingUrl', '');


            if ($orderNumber === null) {
                throw new \InvalidArgumentException('Missing Argument for Order Number!');
            }

            if ($itemIdentifier === null) {
                throw new \InvalidArgumentException('Missing Argument for Item identifier!');
            }

            $shipment = $this->shipmentFacade->shipItemByOrderNumber(
                $orderNumber,
                $itemIdentifier,
                $quantity,
                $trackingCarrier,
                $trackingCode,
                $trackingUrl,
                $context
            );

            return $this->shipmentToJson($shipment);
        } catch (\Exception $e) {
            $data = [
                'orderNumber' => $orderNumber,
                'item' => $itemIdentifier,
                'quantity' => $quantity,
                'trackingCarrier' => $trackingCarrier,
                'trackingCode' => $trackingCode,
                'trackingUrl' => $trackingUrl,
            ];

            return $this->exceptionToJson($e, $data);
        }
    }

    private function shipmentToJson(Shipment $shipment): JsonResponse
    {
        $lines = [];
        /** @var OrderLine $orderLine */
        foreach ($shipment->lines() as $orderLine) {
            $lines[] = [
                'id' => $orderLine->id,
                'orderId' => $orderLine->orderId,
                'name' => $orderLine->name,
                'sku' => $orderLine->sku,
                'type' => $orderLine->type,
                'status' => $orderLine->status,
                'quantity' => $orderLine->quantity,
                'unitPrice' => (array)$orderLine->unitPrice,
                'vatRate' => $orderLine->vatRate,
                'vatAmount' => (array)$orderLine->vatAmount,
                'totalAmount' => (array)$orderLine->totalAmount,
                'createdAt' => $orderLine->createdAt
            ];
        }

        return $this->json([
            'id' => $shipment->id,
            'orderId' => $shipment->orderId,
            'createdAt' => $shipment->createdAt,
            'lines' => $lines,
            'tracking' => $shipment->tracking
        ]);
    }

    /**
     * @param Exception $e
     * @param array<mixed> $additionalData
     * @return JsonResponse
     */
    private function exceptionToJson(Exception $e, array $additionalData = []): JsonResponse
    {
        $this->logger->error(
            $e->getMessage(),
            $additionalData
        );

        return $this->json([
            'error' => get_class($e),
            'message' => $e->getMessage(),
            'data' => $additionalData
        ], 400);
    }

    // Admin routes

    /**
     * @Route("/api/_action/mollie/ship", name="api.action.mollie.ship.order", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function shipOrder(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->getShipOrderResponse(
            $data->getAlnum('orderId'),
            $data->get('trackingCarrier', ''),
            $data->get('trackingCode', ''),
            $data->get('trackingUrl', ''),
            $context
        );
    }

    /**
     * @Route("/api/v{version}/_action/mollie/ship", name="api.action.mollie.ship.order.legacy", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function shipOrderLegacy(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->getShipOrderResponse(
            $data->getAlnum('orderId'),
            $data->get('trackingCarrier', ''),
            $data->get('trackingCode', ''),
            $data->get('trackingUrl', ''),
            $context
        );
    }

    /**
     * @param string $orderId
     * @param string $trackingCarrier
     * @param string $trackingCode
     * @param string $trackingUrl
     * @param Context $context
     * @return JsonResponse
     */
    public function getShipOrderResponse(
        string  $orderId,
        string  $trackingCarrier,
        string  $trackingCode,
        string  $trackingUrl,
        Context $context
    ): JsonResponse {
        try {
            if (empty($orderId)) {
                throw new \InvalidArgumentException('Missing Argument for Order ID!');
            }

            $shipment = $this->shipmentFacade->shipOrderByOrderId(
                $orderId,
                $trackingCarrier,
                $trackingCode,
                $trackingUrl,
                $context
            );

            return $this->shipmentToJson($shipment);
        } catch (\Exception $e) {
            $data = [
                'orderId' => $orderId,
                'trackingCarrier' => $trackingCarrier,
                'trackingCode' => $trackingCode,
                'trackingUrl' => $trackingUrl,
            ];

            return $this->exceptionToJson($e, $data);
        }
    }

    /**
     * @Route("/api/_action/mollie/ship/item", name="api.action.mollie.ship.item", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function shipItem(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->getShipItemResponse(
            $data->getAlnum('orderId'),
            $data->getAlnum('itemId'),
            $data->getInt('quantity'),
            $data->get('trackingCarrier', ''),
            $data->get('trackingCode', ''),
            $data->get('trackingUrl', ''),
            $context
        );
    }

    /**
     * @Route("/api/v{version}/_action/mollie/ship/item", name="api.action.mollie.ship.item.legacy", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function shipItemLegacy(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->getShipItemResponse(
            $data->getAlnum('orderId'),
            $data->getAlnum('itemId'),
            $data->getInt('quantity'),
            $data->get('trackingCarrier', ''),
            $data->get('trackingCode', ''),
            $data->get('trackingUrl', ''),
            $context
        );
    }

    /**
     * @param string $orderId
     * @param string $itemId
     * @param int $quantity
     * @param string $trackingCarrier
     * @param string $trackingCode
     * @param string $trackingUrl
     * @param Context $context
     * @return JsonResponse
     */
    public function getShipItemResponse(
        string  $orderId,
        string  $itemId,
        int     $quantity,
        string  $trackingCarrier,
        string  $trackingCode,
        string  $trackingUrl,
        Context $context
    ): JsonResponse {
        try {
            if (empty($orderId)) {
                throw new \InvalidArgumentException('Missing Argument for Order ID!');
            }

            if (empty($itemId)) {
                throw new \InvalidArgumentException('Missing Argument for Item ID!');
            }

            $shipment = $this->shipmentFacade->shipItemByOrderId(
                $orderId,
                $itemId,
                $quantity,
                $trackingCarrier,
                $trackingCode,
                $trackingUrl,
                $context
            );

            return $this->shipmentToJson($shipment);
        } catch (\Exception $e) {
            $data = [
                'orderId' => $orderId,
                'itemId' => $itemId,
                'quantity' => $quantity,
                'trackingCarrier' => $trackingCarrier,
                'trackingCode' => $trackingCode,
                'trackingUrl' => $trackingUrl,
            ];

            return $this->exceptionToJson($e, $data);
        }
    }

    /**
     * @Route("/api/_action/mollie/ship/status", name="api.action.mollie.ship.status", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function status(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->getStatusResponse($data->get('orderId'), $context);
    }

    /**
     * @Route("/api/v{version}/_action/mollie/ship/status", name="api.action.mollie.ship.status.legacy", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function statusLegacy(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->getStatusResponse($data->get('orderId'), $context);
    }

    /**
     * @param string $orderId
     * @param Context $context
     * @return JsonResponse
     */
    public function getStatusResponse(string $orderId, Context $context): JsonResponse
    {
        try {
            $status = $this->shipmentFacade->getStatus($orderId, $context);
        } catch (ShopwareHttpException $e) {
            $this->logger->error($e->getMessage());
            return $this->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            return $this->json(['message' => $e->getMessage()], 500);
        }

        return $this->json($status);
    }

    /**
     * @Route("/api/_action/mollie/ship/total", name="api.action.mollie.ship.total", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function total(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->getTotalResponse($data->get('orderId'), $context);
    }

    /**
     * @Route("/api/v{version}/_action/mollie/ship/total", name="api.action.mollie.ship.total.legacy", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function totalLegacy(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->getTotalResponse($data->get('orderId'), $context);
    }

    /**
     * @param string $orderId
     * @param Context $context
     * @return JsonResponse
     */
    public function getTotalResponse(string $orderId, Context $context): JsonResponse
    {
        try {
            $totals = $this->shipmentFacade->getTotals($orderId, $context);
        } catch (ShopwareHttpException $e) {
            $this->logger->error($e->getMessage());
            return $this->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            return $this->json(['message' => $e->getMessage()], 500);
        }

        return $this->json($totals);
    }
}
