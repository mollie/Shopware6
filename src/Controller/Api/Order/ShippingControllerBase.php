<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\Order;

use Kiener\MolliePayments\Components\ShipmentManager\Models\ShipmentLineItem;
use Kiener\MolliePayments\Components\ShipmentManager\Models\TrackingData;
use Kiener\MolliePayments\Components\ShipmentManager\ShipmentManager;
use Kiener\MolliePayments\Exception\CouldNotFetchMollieOrderException;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Kiener\MolliePayments\Traits\Api\ApiTrait;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Resources\Shipment;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ShippingControllerBase extends AbstractController
{
    use ApiTrait;

    /**
     * @var ShipmentManager
     */
    private $shipment;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(ShipmentManager $shipmentFacade, OrderService $orderService, LoggerInterface $logger)
    {
        $this->shipment = $shipmentFacade;
        $this->orderService = $orderService;
        $this->logger = $logger;
    }

    public function status(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->getStatusResponse($data->get('orderId'), $context);
    }

    public function statusLegacy(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->getStatusResponse($data->get('orderId'), $context);
    }

    public function total(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->getTotalResponse($data->get('orderId'), $context);
    }

    public function totalLegacy(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->getTotalResponse($data->get('orderId'), $context);
    }

    /**
     * This is the custom operational route for shipping using the API.
     * This shipment is based on ship all or rest of items automatically.
     * It can be used by 3rd parties, ERP systems and more.
     */
    public function shipOrderOperational(Request $request, Context $context): JsonResponse
    {
        $orderNumber = '';
        $trackingCarrier = '';
        $trackingCode = '';
        $trackingUrl = '';

        try {
            $content = (string) $request->getContent();
            $jsonData = json_decode($content, true);

            $orderNumber = (string) $jsonData['orderNumber'];
            $trackingCarrier = (string) $jsonData['trackingCarrier'];
            $trackingCode = (string) $jsonData['trackingCode'];
            $trackingUrl = (string) $jsonData['trackingUrl'];

            if ($orderNumber === '') {
                throw new \InvalidArgumentException('Missing Argument for Order Number!');
            }

            $order = $this->orderService->getOrderByNumber($orderNumber, $context);

            $tracking = new TrackingData($trackingCarrier, $trackingCode, $trackingUrl);

            $shipment = $this->shipment->shipOrderRest(
                $order,
                $tracking,
                $context
            );

            return $this->shipmentToJson($shipment);
        } catch (\Exception $e) {
            $this->logger->error(
                'Error when shipping order: ' . $orderNumber,
                [
                    'error' => $e,
                ]
            );

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
     * This is the custom operational route for shipping using the API.
     * This shipment is based on ship all or rest of items automatically.
     * It can be used by 3rd parties, ERP systems and more.
     * This comes without tracking information. Please use the POST version.
     */
    public function shipOrderOperationalDeprecated(QueryDataBag $query, Context $context): JsonResponse
    {
        $orderNumber = '';

        try {
            $orderNumber = $query->get('number');

            if ($orderNumber === null) {
                throw new \InvalidArgumentException('Missing Argument for Order Number!');
            }

            $order = $this->orderService->getOrderByNumber($orderNumber, $context);

            $shipment = $this->shipment->shipOrderRest(
                $order,
                null,
                $context
            );

            return $this->shipmentToJson($shipment);
        } catch (\Exception $e) {
            $this->logger->error(
                'Error when shipping order (deprecated): ' . $orderNumber,
                [
                    'error' => $e,
                ]
            );

            $data = [
                'orderNumber' => $orderNumber,
            ];

            return $this->exceptionToJson($e, $data);
        }
    }

    /**
     * This is the custom operational route for batch shipping of orders using the API.
     * This shipment requires a valid list of line items to be provided.
     * It can be used by 3rd parties, ERP systems and more.
     */
    public function shipOrderBatchOperational(Request $request, Context $context): JsonResponse
    {
        $orderNumber = '';
        $requestItems = [];
        $trackingCarrier = '';
        $trackingCode = '';
        $trackingUrl = '';

        try {
            $content = (string) $request->getContent();
            $jsonData = json_decode($content, true);

            $orderNumber = (string) $jsonData['orderNumber'];
            $requestItems = $jsonData['items'];
            $trackingCarrier = (string) $jsonData['trackingCarrier'];
            $trackingCode = (string) $jsonData['trackingCode'];
            $trackingUrl = (string) $jsonData['trackingUrl'];

            if (! is_array($requestItems)) {
                $requestItems = [];
            }

            if ($orderNumber === '') {
                throw new \InvalidArgumentException('Missing Argument for Order Number!');
            }

            if (empty($requestItems)) {
                throw new \InvalidArgumentException('Missing Argument for Items!');
            }

            $order = $this->orderService->getOrderByNumber($orderNumber, $context);

            $orderItems = $order->getLineItems();

            if (! $orderItems instanceof OrderLineItemCollection) {
                throw new \Exception('Shopware order does not have any line requestItems!');
            }

            $shipmentItems = [];

            // we need to look up the internal line item ids for the order
            // because we are only provided product numbers
            foreach ($orderItems as $orderItem) {
                foreach ($requestItems as $requestItem) {
                    $orderItemAttr = new OrderLineItemEntityAttributes($orderItem);

                    $productNumber = $requestItem['productNumber'];
                    $quantity = $requestItem['quantity'];

                    // check if we have found our product by number
                    if ($orderItemAttr->getProductNumber() === $productNumber) {
                        $shipmentItems[] = new ShipmentLineItem(
                            $orderItem->getId(),
                            $quantity
                        );
                        break;
                    }
                }
            }

            if (empty($shipmentItems)) {
                throw new \InvalidArgumentException('Provided items have not been found in order!');
            }

            $tracking = new TrackingData($trackingCarrier, $trackingCode, $trackingUrl);

            $shipment = $this->shipment->shipOrder(
                $order,
                $tracking,
                $shipmentItems,
                $context
            );

            return $this->shipmentToJson($shipment);
        } catch (\Exception $e) {
            $this->logger->error(
                'Error when shipping batch order: ' . $orderNumber,
                [
                    'error' => $e,
                ]
            );

            $data = [
                'orderNumber' => $orderNumber,
                'items' => $requestItems,
                'trackingCarrier' => $trackingCarrier,
                'trackingCode' => $trackingCode,
                'trackingUrl' => $trackingUrl,
            ];

            return $this->exceptionToJson($e, $data);
        }
    }

    /**
     * This is the custom operational route for shipping items using the API.
     * It can be used by 3rd parties, ERP systems and more.
     *
     * @throws \Exception
     */
    public function shipItemOperational(Request $request, Context $context): JsonResponse
    {
        $orderNumber = '';
        $itemIdentifier = '';
        $quantity = '';
        $trackingCarrier = '';
        $trackingCode = '';
        $trackingUrl = '';

        try {
            $content = (string) $request->getContent();
            $jsonData = json_decode($content, true);

            $orderNumber = (string) $jsonData['orderNumber'];
            $itemProductNumber = (string) $jsonData['productNumber'];
            $quantity = (int) ($jsonData['quantity'] ?? 0);
            $trackingCarrier = (string) ($jsonData['trackingCarrier'] ?? '');
            $trackingCode = (string) ($jsonData['trackingCode'] ?? '');
            $trackingUrl = (string) ($jsonData['trackingUrl'] ?? '');

            if ($orderNumber === '') {
                throw new \InvalidArgumentException('Missing Argument for Order Number!');
            }

            if ($itemProductNumber === '') {
                throw new \InvalidArgumentException('Missing Argument for item product number!');
            }

            $order = $this->orderService->getOrderByNumber($orderNumber, $context);

            $tracking = new TrackingData($trackingCarrier, $trackingCode, $trackingUrl);

            $shipment = $this->shipment->shipItem(
                $order,
                $itemProductNumber,
                $quantity,
                $tracking,
                $context
            );

            return $this->shipmentToJson($shipment);
        } catch (\Exception $e) {
            $this->logger->error(
                'Error when shipping item of order: ' . $orderNumber,
                [
                    'error' => $e,
                ]
            );

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

    /**
     * This is the custom operational route for shipping items using the API.
     * It can be used by 3rd parties, ERP systems and more.
     *  This comes without tracking information. Please use the POST version.
     *
     * @throws \Exception
     */
    public function shipItemOperationalDeprecated(QueryDataBag $query, Context $context): JsonResponse
    {
        $orderNumber = '';
        $itemIdentifier = '';
        $quantity = '';

        try {
            $orderNumber = $query->get('order');
            $itemIdentifier = $query->get('item');
            $quantity = $query->getInt('quantity');

            if ($orderNumber === '') {
                throw new \InvalidArgumentException('Missing argument for Order Number!');
            }

            if ($itemIdentifier === '') {
                throw new \InvalidArgumentException('Missing argument for Item identifier! Please provide a product number!');
            }

            $order = $this->orderService->getOrderByNumber($orderNumber, $context);

            $shipment = $this->shipment->shipItem(
                $order,
                $itemIdentifier,
                $quantity,
                null,
                $context
            );

            return $this->shipmentToJson($shipment);
        } catch (\Exception $e) {
            $this->logger->error(
                'Error when shipping item of order (deprecated): ' . $orderNumber,
                [
                    'error' => $e,
                ]
            );

            $data = [
                'orderNumber' => $orderNumber,
                'item' => $itemIdentifier,
                'quantity' => $quantity,
            ];

            return $this->exceptionToJson($e, $data);
        }
    }

    /**
     * This is the plain action API route that is used in the Shopware Administration.
     */
    public function shipOrderAdmin(RequestDataBag $data, Context $context): JsonResponse
    {
        $orderId = $data->getAlnum('orderId');
        $trackingCarrier = $data->get('trackingCarrier', '');
        $trackingCode = $data->get('trackingCode', '');
        $trackingUrl = $data->get('trackingUrl', '');
        $itemsBag = $data->get('items', []);

        $items = [];
        if ($itemsBag instanceof RequestDataBag) {
            $items = $itemsBag->all();
        }

        return $this->processAdminShipOrder(
            $orderId,
            $trackingCarrier,
            $trackingCode,
            $trackingUrl,
            $items,
            $context
        );
    }

    public function shipOrderAdminLegacy(RequestDataBag $data, Context $context): JsonResponse
    {
        $orderId = $data->getAlnum('orderId');
        $trackingCarrier = $data->get('trackingCarrier', '');
        $trackingCode = $data->get('trackingCode', '');
        $trackingUrl = $data->get('trackingUrl', '');
        $itemsBag = $data->get('items', []);

        $items = [];
        if ($itemsBag instanceof RequestDataBag) {
            $items = $itemsBag->all();
        }

        return $this->processAdminShipOrder(
            $orderId,
            $trackingCarrier,
            $trackingCode,
            $trackingUrl,
            $items,
            $context
        );
    }

    /**
     * This is the plain action API route that is used in the Shopware Administration.
     */
    public function shipItemAdmin(RequestDataBag $data, Context $context): JsonResponse
    {
        $orderId = $data->getAlnum('orderId');
        $itemId = $data->get('itemId', '');
        $quantity = $data->get('quantity', 0);
        $trackingCarrier = $data->get('trackingCarrier', '');
        $trackingCode = $data->get('trackingCode', '');
        $trackingUrl = $data->get('trackingUrl', '');

        return $this->processShipItem(
            $orderId,
            $itemId,
            $quantity,
            $trackingCarrier,
            $trackingCode,
            $trackingUrl,
            $context
        );
    }

    public function shipItemAdminLegacy(RequestDataBag $data, Context $context): JsonResponse
    {
        $orderId = $data->getAlnum('orderId');
        $itemId = $data->get('itemId', '');
        $quantity = $data->get('quantity', 0);
        $trackingCarrier = $data->get('trackingCarrier', '');
        $trackingCode = $data->get('trackingCode', '');
        $trackingUrl = $data->get('trackingUrl', '');

        return $this->processShipItem(
            $orderId,
            $itemId,
            $quantity,
            $trackingCarrier,
            $trackingCode,
            $trackingUrl,
            $context
        );
    }

    private function getTotalResponse(string $orderId, Context $context): JsonResponse
    {
        try {
            $totals = $this->shipment->getTotals($orderId, $context);
        } catch (ShopwareHttpException $e) {
            $this->logger->error($e->getMessage());

            return $this->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());

            return $this->json(['message' => $e->getMessage()], 500);
        }

        return $this->json($totals);
    }

    private function getStatusResponse(string $orderId, Context $context): JsonResponse
    {
        try {
            $status = $this->shipment->getStatus($orderId, $context);
        } catch (CouldNotFetchMollieOrderException $e) {
            $status = $this->shipment->getShopwareStatus($orderId, $context);
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
     * @param array<mixed> $lineItems
     */
    private function processAdminShipOrder(string $orderId, string $trackingCarrier, string $trackingCode, string $trackingUrl, array $lineItems, Context $context): JsonResponse
    {
        try {
            if (empty($orderId)) {
                throw new \InvalidArgumentException('Missing Argument for Order ID!');
            }

            $order = $this->orderService->getOrder($orderId, $context);

            // hydrate to our real item struct
            $items = $this->hydrateShippingItems($lineItems);

            $tracking = new TrackingData($trackingCarrier, $trackingCode, $trackingUrl);

            $shipment = $this->shipment->shipOrder(
                $order,
                $tracking,
                $items,
                $context
            );

            return $this->shipmentToJson($shipment);
        } catch (\Exception $e) {
            return $this->buildErrorResponse($e->getMessage());
        }
    }

    private function processShipItem(string $orderId, string $itemId, int $quantity, string $trackingCarrier, string $trackingCode, string $trackingUrl, Context $context): JsonResponse
    {
        try {
            if (empty($orderId)) {
                throw new \InvalidArgumentException('Missing Argument for Order ID!');
            }

            if (empty($itemId)) {
                throw new \InvalidArgumentException('Missing Argument for Item ID!');
            }

            $order = $this->orderService->getOrder($orderId, $context);

            $tracking = new TrackingData($trackingCarrier, $trackingCode, $trackingUrl);

            $shipment = $this->shipment->shipItem(
                $order,
                $itemId,
                $quantity,
                $tracking,
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
                'unitPrice' => (array) $orderLine->unitPrice,
                'vatRate' => $orderLine->vatRate,
                'vatAmount' => (array) $orderLine->vatAmount,
                'totalAmount' => (array) $orderLine->totalAmount,
                'createdAt' => $orderLine->createdAt,
            ];
        }

        return $this->json([
            'id' => $shipment->id,
            'orderId' => $shipment->orderId,
            'createdAt' => $shipment->createdAt,
            'lines' => $lines,
            'tracking' => $shipment->tracking,
        ]);
    }

    /**
     * @param array<mixed> $additionalData
     */
    private function exceptionToJson(\Exception $e, array $additionalData = []): JsonResponse
    {
        $this->logger->error(
            $e->getMessage(),
            $additionalData
        );

        return $this->json([
            'error' => get_class($e),
            'message' => $e->getMessage(),
            'data' => $additionalData,
        ], 400);
    }

    /**
     * @param array<mixed> $items
     *
     * @return ShipmentLineItem[]
     */
    private function hydrateShippingItems(array $items): array
    {
        $finalList = [];

        foreach ($items as $item) {
            $finalList[] = new ShipmentLineItem($item['id'], (int) $item['quantity']);
        }

        return $finalList;
    }
}
