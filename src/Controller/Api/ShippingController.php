<?php

namespace Kiener\MolliePayments\Controller\Api;

use Kiener\MolliePayments\Facade\MollieShipment;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\CustomFieldService;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Resources\Shipment;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ShippingController extends AbstractController
{
    public const CUSTOM_FIELDS_KEY_SHIPPED_QUANTITY = 'shippedQuantity';

    private const CUSTOM_FIELDS_KEY_ORDER_ID = 'order_id';
    private const CUSTOM_FIELDS_KEY_ORDER_LINE_ID = 'order_line_id';
    private const CUSTOM_FIELDS_KEY_QUANTITY = 'quantity';
    private const CUSTOM_FIELDS_KEY_SHIPMENT_ID = 'shipment_id';
    private const CUSTOM_FIELDS_KEY_SHIPMENTS = 'shipments';

    private const REQUEST_KEY_ORDER_LINE_ITEM_ID = 'itemId';
    private const REQUEST_KEY_ORDER_LINE_ITEM_VERSION_ID = 'versionId';
    private const REQUEST_KEY_ORDER_LINE_QUANTITY = self::CUSTOM_FIELDS_KEY_QUANTITY;

    private const RESPONSE_KEY_AMOUNT = 'amount';
    private const RESPONSE_KEY_ITEMS = 'items';
    private const RESPONSE_KEY_SUCCESS = 'success';

    private const SHIPPING_DATA_KEY_ID = 'id';
    private const SHIPPING_DATA_KEY_LINES = 'lines';
    private const SHIPPING_DATA_KEY_QUANTITY = self::CUSTOM_FIELDS_KEY_QUANTITY;
    private const SHIPPING_DATA_KEY_TEST_MODE = 'testmode';

    /**
     * @var MollieApiFactory
     */
    private $apiFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderLineItemRepository;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var MollieShipment
     */
    private $shipmentFacade;

    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param MollieApiFactory $apiFactory
     * @param EntityRepositoryInterface $orderLineItemRepository
     * @param OrderService $orderService
     * @param SettingsService $settingsService
     * @param MollieShipment $shipmentFacade
     * @param LoggerInterface $logger
     */
    public function __construct(MollieApiFactory $apiFactory, EntityRepositoryInterface $orderLineItemRepository, OrderService $orderService, SettingsService $settingsService, MollieShipment $shipmentFacade, LoggerInterface $logger)
    {
        $this->apiFactory = $apiFactory;
        $this->orderLineItemRepository = $orderLineItemRepository;
        $this->orderService = $orderService;
        $this->settingsService = $settingsService;

        $this->shipmentFacade = $shipmentFacade;
        $this->logger = $logger;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/mollie/ship/order",
     *         defaults={"auth_enabled"=true}, name="api.mollie.ship.order", methods={"GET"})
     *
     * @param QueryDataBag $query
     * @param Context $context
     * @return JsonResponse
     */
    public function shipOrder(QueryDataBag $query, Context $context): JsonResponse
    {
        try {
            $orderNumber = $query->get('number');

            if ($orderNumber === null) {
                throw new \InvalidArgumentException('Missing Argument for Order Number!');
            }

            $shipment = $this->shipmentFacade->shipOrderByOrderNumber($orderNumber, $context);

            return $this->shipmentToJson($shipment);
        } catch (\Exception $e) {
            $data = [
                'orderNumber' => $orderNumber
            ];

            return $this->exceptionToJson($e, $context, $data);
        }
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/mollie/ship/item",
     *         defaults={"auth_enabled"=true}, name="api.mollie.ship.item", methods={"GET"})
     *
     * @param QueryDataBag $query
     * @param Context $context
     * @return JsonResponse
     * @throws \Exception
     */
    public function shipItem(QueryDataBag $query, Context $context): JsonResponse
    {
        try {
            $orderNumber = $query->get('order');
            $itemIdentifier = $query->get('item');
            $quantity = $query->getInt('quantity');

            if ($orderNumber === null) {
                throw new \InvalidArgumentException('Missing Argument for Order Number!');
            }

            if ($itemIdentifier === null) {
                throw new \InvalidArgumentException('Missing Argument for Item identifier!');
            }

            $shipment = $this->shipmentFacade->shipItemByOrderNumber($orderNumber, $itemIdentifier, $quantity, $context);

            return $this->shipmentToJson($shipment);
        } catch (\Exception $e) {
            $data = [
                'orderNumber' => $orderNumber,
                'item' => $itemIdentifier,
                'quantity' => $quantity
            ];

            return $this->exceptionToJson($e, $context, $data);
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

    private function exceptionToJson(\Exception $e, Context $context, array $additionalData = []): JsonResponse
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
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/mollie/ship/status",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.ship.status", methods={"POST"})
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
     * @RouteScope(scopes={"api"})
     * @Route("/api/v{version}/_action/mollie/ship/status",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.ship.status.legacy", methods={"POST"})
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
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/mollie/ship/total",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.ship.total", methods={"POST"})
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
     * @RouteScope(scopes={"api"})
     * @Route("/api/v{version}/_action/mollie/ship/total",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.ship.total.legacy", methods={"POST"})
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

    /**
     * TODO Refactor Administration routes
     */
    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/mollie/ship",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.ship", methods={"POST"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws ApiException
     * @throws IncompatiblePlatform
     */
    public function ship64(Request $request): JsonResponse
    {
        return $this->shipResponse($request);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/v{version}/_action/mollie/ship",
     *         defaults={"auth_enabled"=true}, name="api.action.pre64.mollie.ship", methods={"POST"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws ApiException
     * @throws IncompatiblePlatform
     */
    public function oldShip(Request $request): JsonResponse
    {
        return $this->shipResponse($request);
    }


    private function shipResponse(Request $request): JsonResponse
    {
        /** @var MollieApiClient|null $apiClient */
        $apiClient = null;

        /** @var array|null $customFields */
        $customFields = null;

        /** @var string|null $orderId */
        $orderId = null;

        /** @var string|null $orderLineId */
        $orderLineId = null;

        /** @var OrderLineItemEntity $orderLineItem */
        $orderLineItem = null;

        /** @var bool $success */
        $success = false;

        /** @var int $quantity */
        $quantity = 0;

        if (
            (string)$request->get(self::REQUEST_KEY_ORDER_LINE_ITEM_ID) !== ''
            && (string)$request->get(self::REQUEST_KEY_ORDER_LINE_ITEM_VERSION_ID) !== ''
        ) {
            $orderLineItem = $this->getOrderLineItemById(
                $request->get(self::REQUEST_KEY_ORDER_LINE_ITEM_ID),
                $request->get(self::REQUEST_KEY_ORDER_LINE_ITEM_VERSION_ID)
            );
        }

        if ((int)$request->get(self::REQUEST_KEY_ORDER_LINE_QUANTITY) > 0) {
            $quantity = (int)$request->get(self::REQUEST_KEY_ORDER_LINE_QUANTITY);
        }

        if (
            $orderLineItem !== null
            && !empty($orderLineItem->getCustomFields())
        ) {
            $customFields = $orderLineItem->getCustomFields();
        }

        if (
            $orderLineItem !== null
            && !empty($customFields)
            && isset($customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][self::CUSTOM_FIELDS_KEY_ORDER_LINE_ID])
        ) {
            $orderLineId = $customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][self::CUSTOM_FIELDS_KEY_ORDER_LINE_ID];
        }

        if (
            $orderLineItem !== null
            && $orderLineItem->getOrder() !== null
            && !empty($orderLineItem->getOrder()->getCustomFields())
            && isset($orderLineItem->getOrder()->getCustomFields()[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][self::CUSTOM_FIELDS_KEY_ORDER_ID])
        ) {
            $orderId = $orderLineItem->getOrder()->getCustomFields()[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][self::CUSTOM_FIELDS_KEY_ORDER_ID];
        }

        if (
            (string)$orderId !== ''
            && (string)$orderLineId !== ''
            && $quantity > 0
        ) {
            $apiClient = $this->apiFactory->getClient(
                $orderLineItem->getOrder()->getSalesChannelId()
            );
        }

        if ($apiClient !== null) {
            /** @var MollieSettingStruct $settings */
            $settings = $this->settingsService->getSettings(
                $orderLineItem->getOrder()->getSalesChannelId()
            );

            /** @var array $orderParameters */
            $orderParameters = [];

            if ($settings->isTestMode() && $apiClient->usesOAuth()) {
                $orderParameters = [
                    self::SHIPPING_DATA_KEY_TEST_MODE => true
                ];
            }

            $order = $apiClient->orders->get($orderId, $orderParameters);

            if ($order !== null && isset($order->id)) {
                $shipmentData = [
                    self::SHIPPING_DATA_KEY_LINES => [
                        [
                            self::SHIPPING_DATA_KEY_ID => $orderLineId,
                            self::SHIPPING_DATA_KEY_QUANTITY => $quantity,
                        ],
                    ],
                ];

                if ($settings->isTestMode() && $apiClient->usesOAuth()) {
                    $shipmentData[self::SHIPPING_DATA_KEY_TEST_MODE] = true;
                }

                $shipment = $apiClient->shipments->createFor($order, $shipmentData);

                if (isset($shipment->id)) {
                    $success = true;

                    if (!isset($customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][self::CUSTOM_FIELDS_KEY_SHIPMENTS])) {
                        $customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][self::CUSTOM_FIELDS_KEY_SHIPMENTS] = [];
                    }

                    if (!is_array($customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][self::CUSTOM_FIELDS_KEY_SHIPMENTS])) {
                        $customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][self::CUSTOM_FIELDS_KEY_SHIPMENTS] = [];
                    }

                    $customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][self::CUSTOM_FIELDS_KEY_SHIPMENTS][] = [
                        self::CUSTOM_FIELDS_KEY_SHIPMENT_ID => $shipment->id,
                        self::CUSTOM_FIELDS_KEY_QUANTITY => $quantity,
                    ];

                    if (isset($customFields[self::CUSTOM_FIELDS_KEY_SHIPPED_QUANTITY])) {
                        $customFields[self::CUSTOM_FIELDS_KEY_SHIPPED_QUANTITY] += $quantity;
                    } else {
                        $customFields[self::CUSTOM_FIELDS_KEY_SHIPPED_QUANTITY] = $quantity;
                    }
                }
            }

            // Update the custom fields of the order line item
            $this->orderLineItemRepository->update([
                [
                    self::SHIPPING_DATA_KEY_ID => $orderLineItem->getId(),
                    CustomFieldService::CUSTOM_FIELDS_KEY => $customFields,
                ]
            ], Context::createDefaultContext());
        }

        return new JsonResponse([
            self::RESPONSE_KEY_SUCCESS => $success,
        ]);
    }

    /**
     * Returns an order line item by id.
     *
     * @param              $lineItemId
     * @param null $versionId
     * @param Context|null $context
     *
     * @return OrderLineItemEntity|null
     */
    public function getOrderLineItemById(
        $lineItemId,
        $versionId = null,
        Context $context = null
    ): ?OrderLineItemEntity
    {
        $orderLineCriteria = new Criteria([$lineItemId]);

        if ($versionId !== null) {
            $orderLineCriteria->addFilter(new EqualsFilter('versionId', $versionId));
        }

        $orderLineCriteria->addAssociation('order');

        return $this->orderLineItemRepository->search(
            $orderLineCriteria,
            $context ?? Context::createDefaultContext()
        )->get($lineItemId);
    }
}
