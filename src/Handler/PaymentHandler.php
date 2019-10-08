<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Handler;

use Exception;
use Kiener\MolliePayments\Helper\PaymentStatusHelper;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\OrderService;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\PaymentStatus;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Language\LanguageEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

class PaymentHandler implements AsynchronousPaymentHandlerInterface
{
    /** @var string */
    protected $paymentMethod;

    /** @var array */
    protected $paymentMethodData = [];

    /** @var OrderTransactionStateHandler */
    private $transactionStateHandler;

    /** @var OrderService */
    private $orderService;

    /** @var CustomerService */
    private $customerService;

    /** @var MollieApiClient */
    private $apiClient;

    /** @var PaymentStatusHelper */
    private $paymentStatusHelper;

    /** @var LoggerInterface */
    private $logger;

    /** @var RouterInterface */
    private $router;

    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        OrderService $orderService,
        CustomerService $customerService,
        MollieApiClient $apiClient,
        PaymentStatusHelper $paymentStatusHelper,
        LoggerInterface $logger,
        RouterInterface $router
    )
    {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->orderService = $orderService;
        $this->customerService = $customerService;
        $this->apiClient = $apiClient;
        $this->paymentStatusHelper = $paymentStatusHelper;
        $this->logger = $logger;
        $this->router = $router;
    }

    /**
     * The pay function will be called after the customer completed the order.
     * Allows to process the order and store additional information.
     *
     * A redirect to the url will be performed
     *
     * Throw a
     * @param AsyncPaymentTransactionStruct $transaction
     * @param RequestDataBag $dataBag
     * @param SalesChannelContext $salesChannelContext
     * @return RedirectResponse @see AsyncPaymentProcessException exception if an error ocurres while processing the payment
     */
    public function pay(
        AsyncPaymentTransactionStruct $transaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext
    ) : RedirectResponse
    {
        /**
         * Prepare the order for the Mollie Orders API and retrieve
         * a payment URL to redirect the customer to in order
         * to finish the payment.
         */
        try {
            $paymentUrl = $this->prepare($transaction, $salesChannelContext);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), [$e]);
        }

        /**
         * Redirect the customer to the payment URL. Afterwards the
         * customer is redirected back to Shopware's finish page, which
         * leads to the @finalize function.
         */
        return RedirectResponse::create($paymentUrl);
    }

    /**
     * The finalize function will be called when the user is redirected back to shop from the payment gateway.
     *
     * Throw a
     * @param AsyncPaymentTransactionStruct $transaction
     * @param Request $request
     * @param SalesChannelContext $salesChannelContext @see AsyncPaymentFinalizeException exception if an error ocurres while calling an external payment API
     * Throw a @throws RuntimeException*@throws CustomerCanceledAsyncPaymentException
     * @throws CustomerCanceledAsyncPaymentException
     * @throws InconsistentCriteriaIdsException
     * @throws \Shopware\Core\System\StateMachine\Exception\IllegalTransitionException
     * @throws \Shopware\Core\System\StateMachine\Exception\StateMachineInvalidEntityIdException
     * @throws \Shopware\Core\System\StateMachine\Exception\StateMachineInvalidStateFieldException
     * @throws \Shopware\Core\System\StateMachine\Exception\StateMachineNotFoundException
     * @see CustomerCanceledAsyncPaymentException exception if the customer canceled the payment process on
     * payment provider page
     */
    public function finalize(AsyncPaymentTransactionStruct $transaction, Request $request, SalesChannelContext $salesChannelContext): void
    {
        /**
         * Retrieve the order from the transaction.
         */
        $order = $transaction->getOrder();

        /**
         * Retrieve the order's custom fields, or set an empty array.
         */
        $orderCustomFields = is_array($order->getCustomFields()) ? $order->getCustomFields() : [];

        /**
         * Retrieve the Mollie Order ID from the order custom fields. We use this
         * to fetch the order from Mollie's Order API and retrieve it's payment status.
         */
        $mollieOrderId = $orderCustomFields['mollie_payments']['order_id'] ?? null;

        if ($mollieOrderId === null) {
            throw new RuntimeException('There is no Mollie order ID stored on order ' . $order->getOrderNumber());
        }

        /**
         * Retrieve the order from Mollie's Orders API, so we can set the status of the order
         * and payment in Shopware.
         */
        try {
            $mollieOrder = $this->apiClient->orders->get($mollieOrderId, [
                'embed' => 'payments'
            ]);
        } catch (ApiException $e) {
            $this->logger->error($e->getMessage(), [$e]);
        }

        /**
         * If the Mollie order can't be fetched, throw an error.
         */
        if (!isset($mollieOrder)) {
            throw new RuntimeException(
                'We can\'t fetch the order ' . $order->getOrderNumber() . ' (' . $mollieOrderId . ') from the Orders API'
            );
        }

        /**
         * Process the payment status of the order. Returns a PaymentStatus string which
         * we can use to throw an exception when the payment is cancelled.
         */
        try {
            $paymentStatus = $this->paymentStatusHelper->processPaymentStatus(
                $transaction->getOrderTransaction()->getId(),
                $order,
                $mollieOrder,
                $salesChannelContext->getContext()
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), [$e]);
        }

        /**
         * If the payment was cancelled by the customer, throw an exception
         * to let the shop handle the cancellation.
         */
        if (isset($paymentStatus) && $paymentStatus === PaymentStatus::STATUS_CANCELED) {
            $this->transactionStateHandler
                ->cancel($transaction->getOrderTransaction()->getId(), $salesChannelContext->getContext());

            throw new CustomerCanceledAsyncPaymentException(
                'Payment for order ' . $order->getOrderNumber() . ' (' . $mollieOrder->id . ') was cancelled by the customer.', ''
            );
        }
    }

    /**
     * Prepares the order payload to send to Mollie's Orders API. Create
     * an order based on this payload and retrieves a payment URL.
     *
     * @param AsyncPaymentTransactionStruct $transaction
     * @param SalesChannelContext $salesChannelContext
     * @return string|null
     * @throws ApiException
     */
    public function prepare(AsyncPaymentTransactionStruct $transaction, SalesChannelContext $salesChannelContext) : ?string
    {
        /**
         * Retrieve the order from the order service in order to
         * get an enriched order entity. This is necessary to have
         * currency, locale and language available in the order entity.
         */
        $order = $this->orderService->getOrder($transaction->getOrder()->getId(), $salesChannelContext->getContext());
        $order = $order ?? $transaction->getOrder();

        /**
         * Retrieve the customer from the customer service in order to
         * get an enriched customer entity. This is necessary to have the
         * customer's addresses available in the customer entity.
         */
        if ($order->getOrderCustomer() !== null) {
            $customer = $this->customerService->getCustomer(
                $order->getOrderCustomer()->getCustomerId(),
                $salesChannelContext->getContext()
            );
        }

        /**
         * If no customer is stored on the order, fallback to the logged in
         * customer in the sales channel context.
         */
        if ($customer === null) {
            $customer = $salesChannelContext->getCustomer();
        }

        /**
         * If the customer isn't present, there is something wrong with the order.
         * Therefore we stop the process.
         */
        if ($customer === null) {
            return null;
        }

        /**
         * Retrieve currency information from the order. This information is
         * necessary for the payload data that is sent to Mollie's Orders API.
         *
         * If the order has no currency, we retrieve it from the sales channel context.
         *
         * @var CurrencyEntity $currency
         */
        $currency = $order->getCurrency();

        if ($currency === null) {
            $currency = $salesChannelContext->getCurrency();
        }

        /**
         * Retrieve locale information from the order. This information is
         * necessary for the payload data that is sent to Mollie's Orders API.
         *
         * Based on this information, Mollie tries to deliver a payment screen
         * in the customer's language.
         *
         * @var LanguageEntity $language
         * @var LocaleEntity $locale
         */
        $locale = $order->getLanguage() !== null ? $order->getLanguage()->getLocale() : null;

        /**
         * Generate the URL for Mollie's webhook call. This webhook is used
         * to handle payment updates.
         *
         * @var $webhookUrl
         */
        $webhookUrl = $this->router->generate('frontend.mollie.webhook', [
            'transactionId' => $transaction->getOrderTransaction()->getId()
        ], $this->router::ABSOLUTE_URL);

        /**
         * Build an array of order data to send in the request
         * to Mollie's Orders API to create an order payment.
         */
        $orderData = [
            'amount' => $this->orderService->getPriceArray(
                $currency !== null ? $currency->getIsoCode() : 'EUR',
                $order->getAmountTotal()
            ),
            'redirectUrl' => $transaction->getReturnUrl(),
            'webhookUrl' => $webhookUrl,
            'locale' => $locale !== null ? $locale->getCode() : null,
            'method' => $this->paymentMethod,
            'orderNumber' => $order->getOrderNumber(),
            'lines' => $this->orderService->getOrderLinesArray($order),
            'billingAddress' => $this->customerService->getAddressArray(
                $customer->getDefaultBillingAddress(),
                $customer
            ),
            'shippingAddress' => $this->customerService->getAddressArray(
                $customer->getDefaultShippingAddress(),
                $customer
            ),
            'payment' => []
        ];

        $orderData = array_merge($orderData, $this->paymentMethodData);

        /**
         * Create an order at Mollie based on the prepared
         * array of order data.
         *
         * @var Order $mollieOrder
         * @throws ApiException
         */
        $mollieOrder = $this->apiClient->orders->create($orderData);

        /**
         * Store the ID of the created order at Mollie on the
         * order in Shopware. We use this identifier to retrieve
         * the order from Mollie after payment to set the order
         * and payment status.
         */
        if (isset($mollieOrder, $mollieOrder->id)) {
            $this->orderService->getRepository()->update([[
                'id' => $order->getId(),
                'customFields' => [
                    'mollie_payments' => [
                        'order_id' => $mollieOrder->id
                    ]
                ]
            ]], $salesChannelContext->getContext());
        }

        /**
         * Return the payment URL from the Mollie order, we redirect
         * the customer to this URL to finish the payment.
         *
         * Afterwards, the customer is redirect to the finish page
         * in Shopware, which leads to @finalize()
         *
         * @var string $paymentUrl
         */
        return isset($mollieOrder) ? $mollieOrder->getCheckoutUrl() : null;
    }
}