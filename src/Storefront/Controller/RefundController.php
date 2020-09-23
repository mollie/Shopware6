<?php

namespace Kiener\MolliePayments\Storefront\Controller;

use Exception;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\CustomFieldService;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\MollieApiClient;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class RefundController extends StorefrontController
{
    public const CUSTOM_FIELDS_KEY_REFUNDED_QUANTITY = 'refundedQuantity';
    public const CUSTOM_FIELDS_KEY_CREATE_CREDIT_ITEM = 'createCredit';

    private const CUSTOM_FIELDS_KEY_ORDER_ID = 'order_id';
    private const CUSTOM_FIELDS_KEY_ORDER_LINE_ID = 'order_line_id';
    private const CUSTOM_FIELDS_KEY_REFUND_ID = 'refund_id';
    private const CUSTOM_FIELDS_KEY_REFUNDS = 'refunds';
    private const CUSTOM_FIELDS_KEY_QUANTITY = 'quantity';

    private const REFUND_DATA_KEY_ID = 'id';
    private const REFUND_DATA_KEY_LINES = 'lines';
    private const REFUND_DATA_KEY_QUANTITY = self::CUSTOM_FIELDS_KEY_QUANTITY;
    private const REFUND_DATA_KEY_TEST_MODE = 'testmode';

    private const REQUEST_KEY_ORDER_LINE_ITEM_ID = 'itemId';
    private const REQUEST_KEY_ORDER_LINE_QUANTITY = self::CUSTOM_FIELDS_KEY_QUANTITY;
    private const REQUEST_KEY_ORDER_LINE_ITEM_VERSION_ID = 'versionId';

    private const RESPONSE_KEY_AMOUNT = 'amount';
    private const RESPONSE_KEY_ITEMS = 'items';
    private const RESPONSE_KEY_SUCCESS = 'success';

    /** @var MollieApiFactory */
    private $apiFactory;

    /** @var EntityRepository */
    private $orderLineItemRepository;

    /** @var OrderService */
    private $orderService;

    /** @var OrderTransactionStateHandler */
    private $orderTransactionStateHandler;

    /** @var SettingsService */
    private $settingsService;

    /**
     * Creates a new instance of the onboarding controller.
     *
     * @param MollieApiFactory             $apiFactory
     * @param EntityRepository             $orderLineItemRepository
     * @param OrderService                 $orderService
     * @param OrderTransactionStateHandler $orderTransactionStateHandler
     * @param SettingsService              $settingsService
     */
    public function __construct(
        MollieApiFactory $apiFactory,
        EntityRepository $orderLineItemRepository,
        OrderService $orderService,
        OrderTransactionStateHandler $orderTransactionStateHandler,
        SettingsService $settingsService
    )
    {
        $this->apiFactory = $apiFactory;
        $this->orderLineItemRepository = $orderLineItemRepository;
        $this->orderService = $orderService;
        $this->orderTransactionStateHandler = $orderTransactionStateHandler;
        $this->settingsService = $settingsService;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/v{version}/_action/mollie/refund",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.refund", methods={"POST"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws ApiException
     * @throws IncompatiblePlatform
     */
    public function refund(Request $request): JsonResponse
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
            (string) $request->get(self::REQUEST_KEY_ORDER_LINE_ITEM_ID) !== ''
            && (string) $request->get(self::REQUEST_KEY_ORDER_LINE_ITEM_VERSION_ID) !== ''
        ) {
            $orderLineItem = $this->getOrderLineItemById(
                $request->get(self::REQUEST_KEY_ORDER_LINE_ITEM_ID),
                $request->get(self::REQUEST_KEY_ORDER_LINE_ITEM_VERSION_ID)
            );
        }

        if ((int) $request->get(self::REQUEST_KEY_ORDER_LINE_QUANTITY) > 0) {
            $quantity = (int) $request->get(self::REQUEST_KEY_ORDER_LINE_QUANTITY);
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

        if ($orderLineItem->getOrder() !== null) {
            $transactions = $orderLineItem->getOrder()->getTransactions();

            if ($transactions !== null && $transactions->count()) {
                foreach ($transactions as $transaction) {
                    try {
                        $this->orderTransactionStateHandler->refundPartially(
                            $transaction->getId(),
                            Context::createDefaultContext()
                        );
                    } catch (Exception $e) {
                        // @todo Maybe handle this exception in debug mode?
                    }
                }
            }
        }

        if (
            (string) $orderId !== ''
            && (string) $orderLineId !== ''
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
                    self::REFUND_DATA_KEY_TEST_MODE => true
                ];
            }

            try {
                $order = $apiClient->orders->get($orderId, $orderParameters);
            } catch (ApiException $e) {
                //
            }

            if (isset($order, $order->id)) {
                $refundData = [
                    self::REFUND_DATA_KEY_LINES => [
                        [
                            self::REFUND_DATA_KEY_ID => $orderLineId,
                            self::REFUND_DATA_KEY_QUANTITY => $quantity,
                        ],
                    ],
                ];

                if ($settings->isTestMode() && $apiClient->usesOAuth()) {
                    $refundData[self::REFUND_DATA_KEY_TEST_MODE] = true;
                }

                try {
                    $refund = $apiClient->orderRefunds->createFor($order, $refundData);
                } catch (ApiException $e) {
                    //
                }

                if (isset($refund, $refund->id)) {
                    $success = true;

                    if (!isset($customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][self::CUSTOM_FIELDS_KEY_REFUNDS])) {
                        $customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][self::CUSTOM_FIELDS_KEY_REFUNDS] = [];
                    }

                    if (!is_array($customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][self::CUSTOM_FIELDS_KEY_REFUNDS])) {
                        $customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][self::CUSTOM_FIELDS_KEY_REFUNDS] = [];
                    }

                    $customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][self::CUSTOM_FIELDS_KEY_REFUNDS][] = [
                        self::CUSTOM_FIELDS_KEY_REFUND_ID => $refund->id,
                        self::CUSTOM_FIELDS_KEY_QUANTITY => $quantity,
                    ];

                    if (isset($customFields[self::CUSTOM_FIELDS_KEY_REFUNDED_QUANTITY])) {
                        $customFields[self::CUSTOM_FIELDS_KEY_REFUNDED_QUANTITY] += $quantity;
                    } else {
                        $customFields[self::CUSTOM_FIELDS_KEY_REFUNDED_QUANTITY] = $quantity;
                    }
                }
            }

            // Update the custom fields of the order line item
            $this->orderLineItemRepository->update([
                [
                    self::REFUND_DATA_KEY_ID => $orderLineItem->getId(),
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
     * @Route("/api/v{version}/_action/mollie/refund/total",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.refund.total", methods={"POST"})
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
                    && isset($lineItem->getCustomFields()[self::CUSTOM_FIELDS_KEY_REFUNDED_QUANTITY])
                ) {
                    $amount += ($lineItem->getUnitPrice() * (int) $lineItem->getCustomFields()[self::CUSTOM_FIELDS_KEY_REFUNDED_QUANTITY]);
                    $items += (int) $lineItem->getCustomFields()[self::CUSTOM_FIELDS_KEY_REFUNDED_QUANTITY];
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
     * @param null         $versionId
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
        $orderLineCriteria->addAssociation('order.salesChannel');
        $orderLineCriteria->addAssociation('order.transactions');

        return $this->orderLineItemRepository->search(
            $orderLineCriteria,
            $context ?? Context::createDefaultContext()
        )->get($lineItemId);
    }
}