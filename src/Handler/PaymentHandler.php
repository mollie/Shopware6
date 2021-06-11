<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Handler;

use Kiener\MolliePayments\Exception\PaymentUrlException;
use Kiener\MolliePayments\Facade\MolliePaymentDoPay;
use Kiener\MolliePayments\Facade\MolliePaymentFinalize;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Helper\PaymentStatusHelper;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\CustomFieldService;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\MollieApi\Order as ApiOrderService;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\Transition\TransactionTransitionServiceInterface;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\OrderLine;
use Monolog\Logger;
use RuntimeException;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\Exception\StateMachineInvalidEntityIdException;
use Shopware\Core\System\StateMachine\Exception\StateMachineInvalidStateFieldException;
use Shopware\Core\System\StateMachine\Exception\StateMachineNotFoundException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

class PaymentHandler implements AsynchronousPaymentHandlerInterface
{
    public const PAYMENT_METHOD_NAME = '';
    public const PAYMENT_METHOD_DESCRIPTION = '';
    protected const FIELD_LOCALE = 'locale';
    protected const FIELD_METHOD = 'method';
    protected const FIELD_ORDER_NUMBER = 'orderNumber';
    protected const FIELD_BILLING_ADDRESS = 'billingAddress';
    protected const FIELD_BILLING_EMAIL = 'billingEmail';
    protected const FIELD_SHIPPING_ADDRESS = 'shippingAddress';
    protected const FIELD_PAYMENT = 'payment';
    protected const FIELD_EXPIRES_AT = 'expiresAt';
    protected const ENV_LOCAL_DEVELOPMENT = 'MOLLIE_LOCAL_DEVELOPMENT';

    /** @var string */
    protected $paymentMethod;

    /** @var array */
    protected $paymentMethodData = [];

    /** @var OrderTransactionStateHandler */
    protected $transactionStateHandler;

    /** @var OrderService */
    protected $orderService;

    /** @var CustomerService */
    protected $customerService;

    /** @var SettingsService */
    protected $settingsService;

    /** @var PaymentStatusHelper */
    protected $paymentStatusHelper;

    /** @var LoggerService */
    protected $logger;

    /** @var RouterInterface */
    protected $router;

    /** @var string $environment */
    protected $environment;

    /**
     * @var ApiOrderService
     */
    private $apiOrderService;
    /**
     * @var MolliePaymentDoPay
     */
    private $payFacade;
    /**
     * @var TransactionTransitionServiceInterface
     */
    private TransactionTransitionServiceInterface $transactionTransitionService;
    /**
     * @var MollieApiFactory
     */
    private MollieApiFactory $mollieApiFactory;
    /**
     * @var MolliePaymentFinalize
     */
    private MolliePaymentFinalize $finalizeFacade;

    /**
     * PaymentHandler constructor.
     *
     * @param OrderTransactionStateHandler $transactionStateHandler
     * @param OrderService $orderService
     * @param CustomerService $customerService
     * @param SettingsService $settingsService
     * @param PaymentStatusHelper $paymentStatusHelper
     * @param LoggerService $logger
     * @param RouterInterface $router
     * @param string $environment
     * @param ApiOrderService $apiOrderService
     * @param MolliePaymentDoPay $payFacade
     * @param MolliePaymentFinalize $finalizeFacade
     * @param TransactionTransitionServiceInterface $transactionTransitionService
     * @param MollieApiFactory $mollieApiFactory
     */
    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        OrderService $orderService,
        CustomerService $customerService,
        SettingsService $settingsService,
        PaymentStatusHelper $paymentStatusHelper,
        LoggerService $logger,
        RouterInterface $router,
        string $environment,
        ApiOrderService $apiOrderService,
        MolliePaymentDoPay $payFacade,
        MolliePaymentFinalize $finalizeFacade,
        TransactionTransitionServiceInterface $transactionTransitionService,
        MollieApiFactory $mollieApiFactory
    )
    {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->orderService = $orderService;
        $this->customerService = $customerService;
        $this->paymentStatusHelper = $paymentStatusHelper;
        $this->logger = $logger;
        $this->router = $router;
        $this->settingsService = $settingsService;
        $this->environment = $environment;
        $this->apiOrderService = $apiOrderService;
        $this->payFacade = $payFacade;
        $this->transactionTransitionService = $transactionTransitionService;
        $this->mollieApiFactory = $mollieApiFactory;
        $this->finalizeFacade = $finalizeFacade;
    }

    /**
     * @param array $orderData
     * @param SalesChannelContext $salesChannelContext
     * @param CustomerEntity $customer
     * @param LocaleEntity $locale
     *
     * @return array
     */
    public function processPaymentMethodSpecificParameters(array $orderData, SalesChannelContext $salesChannelContext, CustomerEntity $customer, LocaleEntity $locale): array
    {
        return [];
    }

    /**
     * The pay function will be called after the customer completed the order.
     * Allows to process the order and store additional information.
     *
     * A redirect to the url will be performed
     *
     * Throw a
     *
     * @param AsyncPaymentTransactionStruct $transaction
     * @param RequestDataBag $dataBag
     * @param SalesChannelContext $salesChannelContext
     *
     * @return RedirectResponse @see AsyncPaymentProcessException exception if an error ocurres while processing the
     *                          payment
     * @throws ApiException
     */
    public function pay(
        AsyncPaymentTransactionStruct $transaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext
    ): RedirectResponse
    {
        try {
            $paymentUrl = $this->payFacade->preparePayProcessAtMollie($this->paymentMethod, $transaction, $salesChannelContext, $this);
        } catch (Throwable $exception) {
            $logException = null;
            $logLevel = Logger::CRITICAL;

            if ($exception instanceof \Exception) {
                $logException = $exception;
                $logLevel = Logger::ERROR;
            }

            $this->logger->addEntry(
                $exception->getMessage(),
                $salesChannelContext->getContext(),
                $logException,
                [
                    'function' => 'order-prepare',
                ],
                $logLevel
            );

            throw new PaymentUrlException($transaction->getOrderTransaction()->getId(), $exception->getMessage());
        }

        try {
            $this->transactionTransitionService->processTransaction($transaction->getOrderTransaction(), $salesChannelContext->getContext());
        } catch (\Exception $exception) {
            // we only log failed transitions
            $this->logger->addEntry(
                sprintf('Could not set payment to in progress. Got error %s', $exception->getMessage()),
                $salesChannelContext->getContext(),
                $exception,
                [
                    'function' => 'order-prepare',
                ],
                Logger::WARNING
            );
        }

        /**
         * Redirect the customer to the payment URL. Afterwards the
         * customer is redirected back to Shopware's finish page, which
         * leads to the @finalize function.
         */
        return new RedirectResponse($paymentUrl);
    }

    /**
     * The finalize function will be called when the user is redirected back to shop from the payment gateway.
     *
     * Throw a
     *
     * @param AsyncPaymentTransactionStruct $transaction
     * @param Request $request
     * @param SalesChannelContext $salesChannelContext @see AsyncPaymentFinalizeException exception if an
     *                                                           error ocurres while calling an external payment API
     *                                                           Throw a @throws RuntimeException*@throws
     *                                                           CustomerCanceledAsyncPaymentException
     *
     * @throws CustomerCanceledAsyncPaymentException
     * @throws InconsistentCriteriaIdsException
     * @throws IllegalTransitionException
     * @throws StateMachineInvalidEntityIdException
     * @throws StateMachineInvalidStateFieldException
     * @throws StateMachineNotFoundException
     * @see CustomerCanceledAsyncPaymentException exception if the customer canceled the payment process on
     * payment provider page
     */
    public function finalize(AsyncPaymentTransactionStruct $transaction, Request $request, SalesChannelContext $salesChannelContext): void
    {
        try {
            $this->finalizeFacade->finalize($transaction, $salesChannelContext);
        } catch (CustomerCanceledAsyncPaymentException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->logger->addEntry(
                $exception->getMessage(),
                $salesChannelContext->getContext(),
                $exception,
                null,
                Logger::ERROR
            );

            throw new CustomerCanceledAsyncPaymentException($transaction->getOrderTransaction()->getId());
        }
    }

    /**
     * Returns a prepared array to create an order at Mollie.
     *
     * @param string $paymentMethod
     * @param string $transactionId
     * @param OrderEntity $order
     * @param string $returnUrl
     * @param SalesChannelContext $salesChannelContext
     *
     * @param array $paymentData
     *
     * @return array
     */
    public function prepareOrderForMollie(
        string $paymentMethod,
        string $transactionId,
        OrderEntity $order,
        string $returnUrl,
        SalesChannelContext $salesChannelContext,
        array $paymentData = []
    ): array
    {
        die('do refactoring first !');
        /** @var MollieSettingStruct $settings */
        $settings = $this->settingsService->getSettings(
            $salesChannelContext->getSalesChannel()->getId(),
            $salesChannelContext->getContext()
        );

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
        if (!isset($customer) || $customer === null) {
            $customer = $salesChannelContext->getCustomer();
        }

        /**
         * If the customer isn't present, there is something wrong with the order.
         * Therefore we stop the process.
         */
        if ($customer === null) {
            throw new \UnexpectedValueException('Customer data could not be found');
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
         * Build an array of order data to send in the request
         * to Mollie's Orders API to create an order payment.
         */
        $orderData = [
//            self::FIELD_AMOUNT => $this->orderService->getPriceArray(
//                $currency !== null ? $currency->getIsoCode() : 'EUR',
//                $order->getAmountTotal()
//            ),
//            self::FIELD_REDIRECT_URL => $this->router->generate('frontend.mollie.payment', [
//                'transactionId' => $transactionId,
//                'returnUrl' => urlencode($returnUrl),
//            ], $this->router::ABSOLUTE_URL),
//            self::FIELD_LOCALE => $locale !== null ? $locale->getCode() : null,
//            self::FIELD_METHOD => $paymentMethod,
//            self::FIELD_ORDER_NUMBER => $order->getOrderNumber(),
//            self::FIELD_LINES => $this->orderService->getOrderLinesArray($order),
//            self::FIELD_BILLING_ADDRESS => $this->customerService->getAddressArray(
//                $customer->getDefaultBillingAddress(),
//                $customer
//            ),
//            self::FIELD_SHIPPING_ADDRESS => $this->customerService->getAddressArray(
//                $customer->getDefaultShippingAddress(),
//                $customer
//            ),
//            self::FIELD_PAYMENT => $paymentData,
        ];

        /**
         * Handle vat free orders.
         */
//        if ($order->getTaxStatus() === CartPrice::TAX_STATE_FREE) {
//            $orderData[self::FIELD_AMOUNT] = $this->orderService->getPriceArray(
//                $currency !== null ? $currency->getIsoCode() : 'EUR',
//                $order->getAmountNet()
//            );
//        }
//
//        /**
//         * Try to fetch the Order Lifetime configuration. If it is can be fetched, set it expiresAt field
//         * The expiresAt is optional and defaults to 28 days if not set
//         */
//        try {
//            $dueDate = $settings->getOrderLifetimeDate();
//
//            if ($dueDate !== null) {
//                $orderData[self::FIELD_EXPIRES_AT] = $dueDate;
//            }
//        } catch (Exception $e) {
//            $this->logger->addEntry(
//                $e->getMessage(),
//                $salesChannelContext->getContext(),
//                $e,
//                [
//                    'function' => 'finalize-payment',
//                ],
//                Logger::ERROR
//            );
//        }

        // Temporarily disabled due to errors with Paypal
        // $orderData = $this->processPaymentMethodSpecificParameters($orderData, $salesChannelContext, $customer, $locale);

        /**
         * Generate the URL for Mollie's webhook call only on prod environment. This webhook is used
         * to handle payment updates.
         */
//        if (
//            getenv(self::ENV_LOCAL_DEVELOPMENT) === false
//            || (bool)getenv(self::ENV_LOCAL_DEVELOPMENT) === false
//        ) {
//            $orderData[self::FIELD_WEBHOOK_URL] = $this->router->generate('frontend.mollie.webhook', [
//                'transactionId' => $transactionId
//            ], $this->router::ABSOLUTE_URL);
//        }

        $customFields = $customer->getCustomFields();

//        // @todo Handle credit card tokens from the Credit Card payment handler
//        if (
//            $this->paymentMethod === PaymentMethod::CREDITCARD
//            && isset($customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][CustomerService::CUSTOM_FIELDS_KEY_CREDIT_CARD_TOKEN])
//            && (string)$customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][CustomerService::CUSTOM_FIELDS_KEY_CREDIT_CARD_TOKEN] !== ''
//        ) {
//            $orderData['payment']['cardToken'] = $customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][CustomerService::CUSTOM_FIELDS_KEY_CREDIT_CARD_TOKEN];
//            $this->customerService->setCardToken($customer, '', $salesChannelContext->getContext());
//        }
//
//        // To connect orders too customers.
//        if (isset($customFields[CustomerService::CUSTOM_FIELDS_KEY_MOLLIE_CUSTOMER_ID])
//            && (string)$customFields[CustomerService::CUSTOM_FIELDS_KEY_MOLLIE_CUSTOMER_ID] !== ''
//            && $settings->createNoCustomersAtMollie() === false
//            && $settings->isTestMode() === false
//        ) {
//            $orderData['payment']['customerId'] = $customFields[CustomerService::CUSTOM_FIELDS_KEY_MOLLIE_CUSTOMER_ID];
//        }


        // @todo Handle iDeal issuers from the iDeal payment handler
//        if (
//            $this->paymentMethod === PaymentMethod::IDEAL
//            && isset($customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][CustomerService::CUSTOM_FIELDS_KEY_PREFERRED_IDEAL_ISSUER])
//            && (string)$customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][CustomerService::CUSTOM_FIELDS_KEY_PREFERRED_IDEAL_ISSUER] !== ''
//        ) {
//            $orderData['payment']['issuer'] = $customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][CustomerService::CUSTOM_FIELDS_KEY_PREFERRED_IDEAL_ISSUER];
//        }


        //@todo don't think its used at all
        //$orderData = array_merge($orderData, $this->paymentMethodData);

        // Log the order data
        if ($settings->isDebugMode()) {
            $this->logger->addEntry(
                sprintf('Order %s is prepared to be paid through Mollie', $order->getOrderNumber()),
                $salesChannelContext->getContext(),
                null,
                [
                    'orderData' => $orderData,
                ]
            );
        }

        return $orderData;
    }

    /**
     * Returns an order that is created through the Mollie API.
     *
     * @param array $orderData
     * @param string $returnUrl
     * @param OrderEntity $order
     * @param SalesChannelContext $salesChannelContext
     *
     * @return Order|null
     *
     * @throws RuntimeException
     */
    public function createOrderAtMollie(array $orderData, string $returnUrl, OrderEntity $order, SalesChannelContext $salesChannelContext): ?Order
    {
        /** @var Order|null $mollieOrder */
        $mollieOrder = null;

        $apiClient = $this->mollieApiFactory->getClient($salesChannelContext->getSalesChannelId(), $salesChannelContext->getContext());
        /**
         * Create an order at Mollie based on the prepared
         * array of order data.
         *
         * @throws ApiException
         * @var Order $mollieOrder
         */
        try {
            $mollieOrder = $apiClient->orders->create($orderData);
        } catch (ApiException $e) {
            $this->logger->addEntry(
                $e->getMessage(),
                $salesChannelContext->getContext(),
                $e,
                [
                    'function' => 'finalize-payment',
                ],
                Logger::ERROR
            );

            throw new RuntimeException(sprintf('Could not create Mollie order, error: %s', $e->getMessage()));
        }

        /**
         * Store the ID of the created order at Mollie on the
         * order in Shopware. We use this identifier to retrieve
         * the order from Mollie after payment to set the order
         * and payment status.
         */
        if (isset($mollieOrder, $mollieOrder->id)) {
            $this->orderService->getOrderRepository()->update([[
                'id' => $order->getId(),
                'customFields' => [
                    CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS => [
                        'order_id' => $mollieOrder->id,
                        'transactionReturnUrl' => $returnUrl,
                    ]
                ]
            ]], $salesChannelContext->getContext());

            // Update the order lines with the corresponding id's from Mollie
            $orderLineUpdate = [];

            /** @var OrderLine $line */
            foreach ($mollieOrder->lines as $line) {
                if (isset($line->metadata->{$this->orderService::ORDER_LINE_ITEM_ID})) {
                    $orderLineUpdate[] = [
                        'id' => $line->metadata->{$this->orderService::ORDER_LINE_ITEM_ID},
                        'customFields' => [
                            CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS => [
                                'order_line_id' => $line->id,
                            ],
                        ],
                    ];
                }
            }

            if (!empty($orderLineUpdate)) {
                $this->orderService->getOrderLineItemRepository()->update(
                    $orderLineUpdate,
                    $salesChannelContext->getContext()
                );
            }
        }

        return $mollieOrder;
    }
}
