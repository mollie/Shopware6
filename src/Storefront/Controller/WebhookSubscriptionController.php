<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Storefront\Controller;

use Exception;
use Kiener\MolliePayments\Exception\PaymentUrlException;
use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\Order\UpdateOrderLineItems;
use Kiener\MolliePayments\Service\TransactionService;
use Kiener\MolliePayments\Service\UpdateOrderCustomFields;
use Kiener\MolliePayments\Struct\MollieOrderCustomFieldsStruct;
use Mollie\Api\Resources\Order as MollieOrder;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderBuilder;
use Kiener\MolliePayments\Service\LoggerService;

class WebhookSubscriptionController extends StorefrontController
{
    public const ORIGINAL_ID = 'originalId';

    public const ORIGINAL_ORDER_NUMBER = 'originalOrderNumber';

    /**
     * @var TransactionService
     */
    private $transactionService;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var NumberRangeValueGeneratorInterface
     */
    private $numberRangeValueGenerator;

    /**
     * @var OrderConverter
     */
    private $orderConverter;

    /**
     * @var Processor
     */
    private $processor;

    /**
     * @var MollieOrderBuilder
     */
    private $orderBuilder;

    /**
     * @var LoggerService
     */
    private $logger;

    /**
     * @var Order
     */
    private $orderApiService;

    /**
     * @var UpdateOrderCustomFields
     */
    private $updateOrderCustomFields;

    /**
     * @var UpdateOrderLineItems
     */
    private $updateOrderLineItems;

    /**
     * @param TransactionService $transactionService
     * @param EntityRepositoryInterface $repository
     * @param NumberRangeValueGeneratorInterface $numberRangeValueGenerator
     * @param OrderConverter $orderConverter
     * @param Processor $processor
     * @param MollieOrderBuilder $orderBuilder
     * @param LoggerService $logger
     * @param Order $orderApiService
     * @param UpdateOrderCustomFields $updateOrderCustomFields
     * @param UpdateOrderLineItems $updateOrderLineItems
     */
    public function __construct(
        TransactionService $transactionService,
        EntityRepositoryInterface $repository,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        OrderConverter $orderConverter,
        Processor $processor,
        MollieOrderBuilder $orderBuilder,
        LoggerService $logger,
        Order $orderApiService,
        UpdateOrderCustomFields $updateOrderCustomFields,
        UpdateOrderLineItems $updateOrderLineItems
    ) {
        $this->transactionService = $transactionService;
        $this->orderRepository = $repository;
        $this->numberRangeValueGenerator = $numberRangeValueGenerator;
        $this->orderConverter = $orderConverter;
        $this->processor = $processor;
        $this->orderBuilder = $orderBuilder;
        $this->logger = $logger;
        $this->orderApiService = $orderApiService;
        $this->updateOrderCustomFields = $updateOrderCustomFields;
        $this->updateOrderLineItems = $updateOrderLineItems;
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie-subscriptions/webhook/{transactionId}", defaults={"csrf_protected"=false},
     *                                           name="frontend.mollie.subscriptions.webhook",
     *                                           options={"seo"="false"}, methods={"GET", "POST"})
     *
     * @param SalesChannelContext $context
     * @param                     $transactionId
     *
     * @return JsonResponse
     * @throws Exception
     */
    public function webhookCall(SalesChannelContext $context, $transactionId): JsonResponse
    {
        $transaction = $this->getTransaction($transactionId, $context->getContext());

        $order = $this->getOrder($transaction->getOrder()->getId());
        $newOrderNumber = $this->numberRangeValueGenerator->getValue(
            'order',
            $context->getContext(),
            $context->getSalesChannel()->getId()
        );
        $newOrderId = $this->createOrder($newOrderNumber, $order, Context::createDefaultContext());
        if(isset($transaction->getOrder()->getCustomFields()['mollie_payments'])) {
            $this->molliePaymentDoPay($newOrderId, $transaction, $context);
        }

        return new JsonResponse(['newOrderId' => $newOrderId]);
    }

    /**
     * @param string $newOrderNumber
     * @param OrderEntity $order
     * @param Context $context
     * @return string
     */
    private function createOrder(string $newOrderNumber, OrderEntity $order, Context $context)
    {
        $salesChannelContext = $this->orderConverter->assembleSalesChannelContext($order, $context);
        $cart = $this->orderConverter->convertToCart($order, $context);
        $recalculatedCart = $this->refresh($cart, $salesChannelContext);

        $conversionContext = (new OrderConversionContext())
            ->setIncludeCustomer(false)
            ->setIncludeBillingAddress(false)
            ->setIncludeDeliveries(true)
            ->setIncludeTransactions(false)
            ->setIncludeOrderDate(false);

        $orderData = $this->orderConverter->convertToOrder($recalculatedCart, $salesChannelContext, $conversionContext);
        $orderData['id'] =  Uuid::randomHex();
        $orderData['orderNumber'] = $newOrderNumber;
        $orderData['billingAddressId'] = $order->getBillingAddressId();
        $orderData['orderDateTime'] = $order->getOrderDateTime();
        $orderData['orderCustomer'] = $this->getOrderCustomer($order->getOrderCustomer());
        $orderData['price'] = $order->getPrice();
        $orderData['shippingCosts'] = $order->getShippingCosts();

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($orderData): void {
            $this->orderRepository->create([$orderData], $context);
        });

        return $orderData['id'];
    }

    /**
     * @param string $newOrderId
     * @param $transactionData
     * @param $salesChannelContext
     * @throws Exception
     */
    private function molliePaymentDoPay(string $newOrderId, $transactionData, $salesChannelContext): string
    {
        // get order with all needed associations
        $order = $this->getOrder($newOrderId, null);

        if (!$order instanceof OrderEntity) {
            throw new OrderNotFoundException($newOrderId);
        }

        $paymentMethod = $transactionData->getPaymentMethod()->getCustomFields()['mollie_payment_method_name'];
        $returnUrl = $transactionData->getOrder()->getCustomFields()['mollie_payments']['transactionReturnUrl'];

        $customFields = $order->getCustomFields() ?? [];
        $customFieldsStruct = new MollieOrderCustomFieldsStruct($customFields);
        $customFieldsStruct->setTransactionReturnUrl($returnUrl);
        $mollieOrderId = $customFieldsStruct->getMollieOrderId();

        // do another payment if mollie order could be found
        if (!empty($mollieOrderId)) {
            $this->logger->addEntry(
                sprintf('Found an existing mollie order with id %s.', $mollieOrderId),
                $salesChannelContext->getSalesChannel()->getId(),
                $salesChannelContext->getContext()
            );

            $payment = $this->orderApiService->createOrReusePayment($mollieOrderId, $paymentMethod, $salesChannelContext);

            // if direct payment return to success page
            if (MolliePaymentStatus::isApprovedStatus($payment->status) && empty($payment->getCheckoutUrl())) {

                return $returnUrl;
            }

            $url = $payment->getCheckoutUrl();

            if (empty($url)) {
                throw new PaymentUrlException(
                    $transactionData->getId(),
                    "Couldn't get mollie payment checkout url"
                );
            }

            $customFieldsStruct->setMolliePaymentUrl($url);
            // save customfields because shopware return url could have changed
            // e.g. if changedPayment Parameter has to be added the shopware payment token changes
            $this->updateOrderCustomFields->updateOrder($order->getId(), $customFieldsStruct, $salesChannelContext);

            return $url;
        }

        // build new mollie order array
        $mollieOrderArray = $this->orderBuilder->build(
            $order,
            $transactionData->getId(),
            $paymentMethod,
            $returnUrl,
            $salesChannelContext,
            null
        );

        $this->logger->addEntry(
            'Created order array for mollie',
            Context::createDefaultContext(),
            null,
            $mollieOrderArray
        );

        // create new order at mollie
        $mollieOrder = $this->orderApiService->createOrder($mollieOrderArray, $order->getSalesChannelId(), $salesChannelContext);

        if ($mollieOrder instanceof MollieOrder) {
            $customFieldsStruct->setMollieOrderId($mollieOrder->id);
            $customFieldsStruct->setMolliePaymentUrl($mollieOrder->getCheckoutUrl());

            $this->updateOrderCustomFields->updateOrder($order->getId(), $customFieldsStruct, $salesChannelContext);
            $this->updateOrderLineItems->updateOrderLineItems($mollieOrder, $salesChannelContext);
        }

        return $customFieldsStruct->getMolliePaymentUrl()
            ?? $customFieldsStruct->getTransactionReturnUrl()
            ?? $returnUrl;
    }

    /**
     * @param string|null $orderId
     * @return OrderEntity|null
     */
    private function getOrder(?string $orderId): ?OrderEntity
    {
        $criteria = (new Criteria([$orderId]));
        $criteria->addAssociation('transactions.stateMachineState');
        $criteria->addAssociation('transactions.paymentMethod');
        $criteria->addAssociation('orderCustomer.customer');
        $criteria->addAssociation('orderCustomer.salutation');
        $criteria->addAssociation('transactions.paymentMethod.appPaymentMethod.app');
        $criteria->addAssociation('language');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('deliveries');
        $criteria->addAssociation('billingAddress.country');
        $criteria->addAssociation('lineItems');

        return $this->orderRepository->search($criteria, Context::createDefaultContext())->get($orderId);
    }

    /**
     * @param Cart $cart
     * @param SalesChannelContext $context
     * @return Cart
     */
    private function refresh(Cart $cart, SalesChannelContext $context): Cart
    {
        $behavior = new CartBehavior($context->getPermissions());
        return $this->processor->process($cart, $context, $behavior);
    }

    /**
     * @param $orderCustomer
     * @return array
     */
    private function getOrderCustomer($orderCustomer): array
    {
        return [
            'customerId' => $orderCustomer->getCustomerId(),
            'email' => $orderCustomer->getEmail(),
            'salutationId' => $orderCustomer->getSalutationId(),
            'firstName' => $orderCustomer->getFirstName(),
            'lastName' => $orderCustomer->getLastName()
        ];
    }

    /**
     * @param $transactionId
     * @param $context
     * @return OrderTransactionEntity|null
     */
    private function getTransaction($transactionId, $context): ?OrderTransactionEntity
    {
        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation('order');
        $criteria->addAssociation('paymentMethod.appPaymentMethod.app');
        /** @var OrderTransactionEntity|null $orderTransaction */
        return $this->transactionService->getRepository()->search($criteria, $context)->first();
    }
}
