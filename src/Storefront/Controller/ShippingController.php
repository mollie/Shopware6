<?php

namespace Kiener\MolliePayments\Storefront\Controller;

use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\CustomFieldService;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\MollieApiClient;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ShippingController extends StorefrontController
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

    /** @var MollieApiFactory */
    private $apiFactory;

    /** @var EntityRepositoryInterface */
    private $orderLineItemRepository;

    /** @var OrderService */
    private $orderService;

    /** @var SettingsService */
    private $settingsService;

    /**
     * Creates a new instance of the onboarding controller.
     *
     * @param MollieApiFactory $apiFactory
     * @param EntityRepositoryInterface $orderLineItemRepository
     * @param OrderService $orderService
     * @param SettingsService $settingsService
     */
    public function __construct(
        MollieApiFactory $apiFactory,
        EntityRepositoryInterface $orderLineItemRepository,
        OrderService $orderService,
        SettingsService $settingsService
    )
    {
        $this->apiFactory = $apiFactory;
        $this->orderLineItemRepository = $orderLineItemRepository;
        $this->orderService = $orderService;
        $this->settingsService = $settingsService;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/v{version}/_action/mollie/ship",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.ship", methods={"POST"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws ApiException
     * @throws IncompatiblePlatform
     */
    public function ship(Request $request): JsonResponse
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
            $apiClient = $this->apiFactory->createClient(
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
     * @RouteScope(scopes={"api"})
     * @Route("/api/v{version}/_action/mollie/ship/total",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.ship.total", methods={"POST"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function total(Request $request): JsonResponse
    {
        /** @var float $amount */
        $amount = 0.0;

        /** @var int $items */
        $items = 0;

        /** @var OrderEntity $order */
        $order = null;

        /** @var string $orderId */
        $orderId = $request->get('orderId');

        if ($orderId !== '') {
            $order = $this->orderService->getOrder($orderId, Context::createDefaultContext());
        }

        if ($order !== null) {
            foreach ($order->getLineItems() as $lineItem) {
                if (
                    !empty($lineItem->getCustomFields())
                    && isset($lineItem->getCustomFields()[self::CUSTOM_FIELDS_KEY_SHIPPED_QUANTITY])
                ) {
                    $amount += ($lineItem->getUnitPrice() * (int)$lineItem->getCustomFields()[self::CUSTOM_FIELDS_KEY_SHIPPED_QUANTITY]);
                    $items += (int)$lineItem->getCustomFields()[self::CUSTOM_FIELDS_KEY_SHIPPED_QUANTITY];
                }
            }
        }

        return new JsonResponse([
            self::RESPONSE_KEY_AMOUNT => $amount,
            self::RESPONSE_KEY_ITEMS => $items,
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
