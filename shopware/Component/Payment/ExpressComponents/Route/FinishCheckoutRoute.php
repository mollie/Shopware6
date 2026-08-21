<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents\Route;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Gateway\SessionGateway;
use Mollie\Shopware\Component\Mollie\Gateway\SessionGatewayInterface;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\Session;
use Mollie\Shopware\Component\Mollie\ShippingOption;
use Mollie\Shopware\Component\Payment\ExpressComponents\ExpressComponentsException;
use Mollie\Shopware\Component\Payment\ExpressComponents\OrderAddressSynchronizer;
use Mollie\Shopware\Component\Payment\ExpressComponents\OrderAddressSynchronizerInterface;
use Mollie\Shopware\Component\Payment\ExpressComponents\SessionBuilder;
use Mollie\Shopware\Component\Payment\ExpressMethod\AbstractAccountService;
use Mollie\Shopware\Component\Payment\ExpressMethod\AccountService;
use Mollie\Shopware\Component\Payment\PaymentMethodRepository;
use Mollie\Shopware\Component\Payment\PaymentMethodRepositoryInterface;
use Mollie\Shopware\Component\Router\RouteBuilder;
use Mollie\Shopware\Component\Router\RouteBuilderInterface;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Transaction\TransactionService;
use Mollie\Shopware\Component\Transaction\TransactionServiceInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartOrderRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartOrderRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractSetPaymentOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\SetPaymentOrderRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractHandlePaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\HandlePaymentMethodRoute;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Entry point Mollie redirects to once the shopper completed the payment inside the
 * express component.
 *
 * At that point there is no order yet, only a cart, and a cart cannot be looked up by the
 * Mollie session id: the session lives in the cart payload, which is stored as a blob. The
 * cart token is therefore part of the redirect url. It also tells a later step whether the
 * checkout started from a cart or from an existing order.
 *
 * The route is a mix of the two existing express flows: the customer data comes out of the
 * Mollie session like in PayPal express, the order is then created from the cart like in
 * Apple Pay direct. The payment itself already exists on Mollie's side, so it is only
 * attached to the order instead of being created again.
 */
#[AsController]
#[Route(defaults: ['_routeScope' => ['store-api']])]
final class FinishCheckoutRoute extends AbstractFinishCheckoutRoute
{
    public const CART_TOKEN_PARAMETER = 'cartToken';
    public const ORDER_ID_PARAMETER = 'orderId';
    public const FINISH_URL_PARAMETER = 'finishUrl';
    public const ERROR_URL_PARAMETER = 'errorUrl';

    /**
     * Placeholder a client may use in its own finish and error url. The order does not exist yet
     * when the client calls this route, so it cannot build the id into the url itself.
     */
    public const ORDER_ID_PLACEHOLDER = '{orderId}';

    /**
     * @param EntityRepository<OrderCollection<OrderEntity>> $orderRepository
     */
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: SessionGateway::class)]
        private SessionGatewayInterface $sessionGateway,
        #[Autowire(service: MollieGateway::class)]
        private MollieGatewayInterface $mollieGateway,
        #[Autowire(service: AccountService::class)]
        private AbstractAccountService $accountService,
        #[Autowire(service: OrderAddressSynchronizer::class)]
        private OrderAddressSynchronizerInterface $orderAddressSynchronizer,
        #[Autowire(service: PaymentMethodRepository::class)]
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        #[Autowire(service: TransactionService::class)]
        private TransactionServiceInterface $transactionService,
        #[Autowire(service: CartOrderRoute::class)]
        private AbstractCartOrderRoute $cartOrderRoute,
        #[Autowire(service: HandlePaymentMethodRoute::class)]
        private AbstractHandlePaymentMethodRoute $handlePaymentMethodRoute,
        #[Autowire(service: SetPaymentOrderRoute::class)]
        private AbstractSetPaymentOrderRoute $setPaymentOrderRoute,
        #[Autowire(service: RouteBuilder::class)]
        private RouteBuilderInterface $routeBuilder,
        #[Autowire(service: 'order.repository')]
        private EntityRepository $orderRepository,
        #[Autowire(service: SalesChannelContextService::class)]
        private SalesChannelContextServiceInterface $salesChannelContextService,
        #[Autowire(service: ContextSwitchRoute::class)]
        private AbstractContextSwitchRoute $contextSwitchRoute,
        private CartService $cartService,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public function getDecorated(): AbstractFinishCheckoutRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(name: 'store-api.mollie.express-components.checkout.finish', path: '/store-api/mollie/express-components/finish', methods: ['GET', 'POST'])]
    public function finishCheckout(Request $request, SalesChannelContext $salesChannelContext): FinishCheckoutResponse
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $settings = $this->settingsService->getExpressComponentsSettings($salesChannelId);
        if ($settings->isEnabled() === false) {
            throw ExpressComponentsException::notEnabled($salesChannelId);
        }

        // the edit order page has no cart, there the order takes the place of the cart token
        $orderId = (string) $request->get(self::ORDER_ID_PARAMETER, '');
        if ($orderId !== '') {
            return $this->finishOrderCheckout($request, $orderId, $salesChannelContext);
        }

        $cartToken = (string) $request->get(self::CART_TOKEN_PARAMETER, '');
        if ($cartToken === '') {
            throw ExpressComponentsException::cartTokenIsEmpty();
        }

        $logData = [
            'cartToken' => $cartToken,
            'salesChannelId' => $salesChannelId,
        ];
        $this->logger->info('Start - finish express components checkout', $logData);

        // Mollie redirects the shopper back without the cart he started from, so the context
        // of that cart is restored from the token that was handed to the redirect url
        $cartContext = $this->restoreContext($cartToken, $salesChannelContext);
        $cart = $this->cartService->getCart($cartToken, $cartContext);

        $mode = $this->settingsService->getApiSettings($salesChannelId)->getMode();
        $storedSession = $cart->getExtension(SessionBuilder::cartExtensionKey($mode));
        if (! $storedSession instanceof Session) {
            throw ExpressComponentsException::cartSessionIdIsEmpty($cartToken);
        }

        $session = $this->sessionGateway->getSession($storedSession->getId(), $cartContext);
        $logData['sessionId'] = $session->getId();

        if (! $session->getStatus()->isCompleted()) {
            $this->logger->error('Express components session is not completed', $logData);
            throw ExpressComponentsException::sessionNotCompleted($session->getId(), $session->getStatus()->value);
        }

        $billingAddress = $session->getBillingAddress();
        $shippingAddress = $session->getShippingAddress();
        if (! $billingAddress instanceof Address || ! $shippingAddress instanceof Address) {
            $this->logger->error('Express components session carries no addresses', $logData);
            throw ExpressComponentsException::addressMissing($session->getId());
        }

        $paymentMethodId = $this->getPaymentMethodId($session, $cartContext);
        $logData['paymentMethodId'] = $paymentMethodId;

        // The data protection checkbox is never sent for this flow: the payment happens inside the
        // Mollie component and Mollie sends the shopper back with a plain redirect, so there is no
        // request left that could carry the consent. It is therefore not accepted, and a shop that
        // requires the checkbox rejects the guest registration instead of consent being faked.
        $orderContext = $this->accountService->loginOrCreateAccount($paymentMethodId, $billingAddress, $shippingAddress, false, $cartContext);
        $logData['customerId'] = $orderContext->getCustomer()?->getId();
        $this->logger->debug('Express components guest account created or logged in', $logData);

        $orderContext = $this->applyShippingMethod($session, $orderContext, $logData);

        // the account and the shipping method changed the context, so the cart has to be
        // recalculated before it becomes an order
        $orderCart = $this->cartService->getCart($orderContext->getToken(), $orderContext);
        $orderResponse = $this->cartOrderRoute->order($orderCart, $orderContext, new RequestDataBag());
        $order = $orderResponse->getOrder();

        $logData['orderId'] = $order->getId();
        $logData['orderNumber'] = $order->getOrderNumber();
        $this->logger->debug('Express components order created', $logData);

        $transaction = $this->getLatestTransaction($order);
        if (! $transaction instanceof OrderTransactionEntity) {
            throw ExpressComponentsException::orderTransactionMissing($order->getId());
        }

        $this->attachPayment($session, $order, $transaction, $orderContext, $logData);
        $redirectUrl = $this->handlePayment($request, $order->getId(), $orderContext, $logData);

        $this->logger->info('Finished - finish express components checkout', $logData);

        return new FinishCheckoutResponse(
            $session->getId(),
            $orderContext->getToken(),
            $order->getId(),
            (string) $order->getOrderNumber(),
            $redirectUrl
        );
    }

    /**
     * The order already exists, so there is nothing to create: the payment of the completed
     * session is attached to it and the regular payment handling patches it and moves the
     * transaction on. The shipping method stays as it is, it is part of the order - the addresses
     * however are taken over from the session, because the shopper picked them in the wallet.
     */
    private function finishOrderCheckout(Request $request, string $orderId, SalesChannelContext $salesChannelContext): FinishCheckoutResponse
    {
        $logData = [
            'orderId' => $orderId,
            'salesChannelId' => $salesChannelContext->getSalesChannelId(),
        ];
        $this->logger->info('Start - finish express components checkout for an existing order', $logData);

        $order = $this->getOrder($orderId, $salesChannelContext);
        $logData['orderNumber'] = $order->getOrderNumber();

        $mode = $this->settingsService->getApiSettings($salesChannelContext->getSalesChannelId())->getMode();
        $sessionId = SessionBuilder::readOrderSessionId($order->getCustomFields() ?? [], $mode);
        if ($sessionId === null) {
            throw ExpressComponentsException::orderSessionIdIsEmpty($orderId);
        }

        $session = $this->sessionGateway->getSession($sessionId, $salesChannelContext);
        $logData['sessionId'] = $session->getId();

        if (! $session->getStatus()->isCompleted()) {
            $this->logger->error('Express components session is not completed', $logData);
            throw ExpressComponentsException::sessionNotCompleted($session->getId(), $session->getStatus()->value);
        }

        $billingAddress = $session->getBillingAddress();
        $shippingAddress = $session->getShippingAddress();
        if (! $billingAddress instanceof Address || ! $shippingAddress instanceof Address) {
            $this->logger->error('Express components session carries no addresses', $logData);
            throw ExpressComponentsException::addressMissing($session->getId());
        }

        $paymentMethodId = $this->getPaymentMethodId($session, $salesChannelContext);
        $logData['paymentMethodId'] = $paymentMethodId;

        // the address the shopper picked in the wallet wins over the one on the account, so it is
        // written onto the customer first and from there onto the order
        // no consent is needed here, the edit order page belongs to a customer that is logged in,
        // so no guest account is ever registered
        $salesChannelContext = $this->accountService->loginOrCreateAccount($paymentMethodId, $billingAddress, $shippingAddress, false, $salesChannelContext);
        $this->orderAddressSynchronizer->sync($order, $salesChannelContext);

        // The order may already carry the transaction of a failed attempt. Shopware answers a new
        // attempt with a new transaction instead of overwriting the old one, so the same route is
        // used here - it keeps the history and makes the new transaction the primary one.
        $setPaymentRequest = new Request();
        $setPaymentRequest->request->set('orderId', $orderId);
        $setPaymentRequest->request->set('paymentMethodId', $paymentMethodId);
        $this->setPaymentOrderRoute->setPayment($setPaymentRequest, $salesChannelContext);

        $order = $this->getOrder($orderId, $salesChannelContext);
        $transaction = $this->getLatestTransaction($order);
        if (! $transaction instanceof OrderTransactionEntity) {
            throw ExpressComponentsException::orderTransactionMissing($orderId);
        }

        $logData['transactionId'] = $transaction->getId();

        $this->attachPayment($session, $order, $transaction, $salesChannelContext, $logData);
        $redirectUrl = $this->handlePayment($request, $orderId, $salesChannelContext, $logData);

        $this->logger->info('Finished - finish express components checkout for an existing order', $logData);

        return new FinishCheckoutResponse(
            $session->getId(),
            $salesChannelContext->getToken(),
            $orderId,
            (string) $order->getOrderNumber(),
            $redirectUrl
        );
    }

    /**
     * The newest transaction is the one of the current attempt. Sorting by creation date works on
     * every supported Shopware version, unlike primaryOrderTransactionId.
     */
    private function getLatestTransaction(OrderEntity $order): ?OrderTransactionEntity
    {
        $transactions = $order->getTransactions();
        if ($transactions === null) {
            return null;
        }

        $sorted = $transactions->getElements();
        uasort($sorted, static function (OrderTransactionEntity $left, OrderTransactionEntity $right): int {
            return ($right->getCreatedAt()?->getTimestamp() ?? 0) <=> ($left->getCreatedAt()?->getTimestamp() ?? 0);
        });

        $latest = reset($sorted);

        return $latest instanceof OrderTransactionEntity ? $latest : null;
    }

    private function getOrder(string $orderId, SalesChannelContext $salesChannelContext): OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions.stateMachineState');
        $criteria->addAssociation('deliveries.shippingMethod');
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('currency');

        $order = $this->orderRepository->search($criteria, $salesChannelContext->getContext())->first();
        if (! $order instanceof OrderEntity) {
            throw ExpressComponentsException::orderNotFound($orderId);
        }

        return $order;
    }

    /**
     * Runs the regular Shopware payment handling on the fresh order, which is what patches the
     * Mollie payment with everything only Shopware knows: the description built from the order
     * number, the webhook url and the metadata. Because the payment id is already on the
     * transaction, the Pay action updates that payment instead of creating one.
     *
     * finishUrl and errorUrl end up in the payment token and are where Shopware sends the shopper
     * after the finalize. Like in Shopware's own store-api they can be passed in, so a headless
     * client points at its own pages instead of storefront routes that do not exist there. Only
     * when they are absent do we fall back to the storefront pages.
     *
     * A failure here must not abort the checkout: the shopper already paid and the order exists.
     *
     * @param array<mixed> $logData
     */
    private function handlePayment(Request $request, string $orderId, SalesChannelContext $salesChannelContext, array $logData): string
    {
        $finishUrl = $this->resolveUrl($request, self::FINISH_URL_PARAMETER, $orderId)
            ?? $this->routeBuilder->getCheckoutFinishUrl($orderId);
        $errorUrl = $this->resolveUrl($request, self::ERROR_URL_PARAMETER, $orderId)
            ?? $this->routeBuilder->getEditOrderUrl($orderId);

        $paymentRequest = new Request();
        $paymentRequest->request->set('orderId', $orderId);
        $paymentRequest->request->set(self::FINISH_URL_PARAMETER, $finishUrl);
        $paymentRequest->request->set(self::ERROR_URL_PARAMETER, $errorUrl);

        try {
            $handlePaymentResponse = $this->handlePaymentMethodRoute->load($paymentRequest, $salesChannelContext);
            $this->logger->debug('Express components payment handled', $logData);

            $redirectResponse = $handlePaymentResponse->getRedirectResponse();
            if ($redirectResponse instanceof RedirectResponse) {
                return $redirectResponse->getTargetUrl();
            }
        } catch (\Throwable $exception) {
            $logData['error'] = $exception->getMessage();
            $this->logger->error('Failed to handle the express components payment', $logData);
        }

        return $finishUrl;
    }

    /**
     * The payment already exists on Mollie's side, it is loaded once and written onto the
     * order transaction the same way a regular payment would be, so webhooks, refunds and the
     * ERP exports find it under the usual keys.
     *
     * @param array<mixed> $logData
     */
    private function attachPayment(Session $session, OrderEntity $order, OrderTransactionEntity $transaction, SalesChannelContext $salesChannelContext, array $logData): void
    {
        $paymentId = $session->getPaymentId();
        if ($paymentId === '') {
            $this->logger->error('Express components session carries no payment id', $logData);

            return;
        }

        $transactionId = $transaction->getId();
        $payment = $this->mollieGateway->getPayment($paymentId, (string) $order->getOrderNumber(), $salesChannelContext->getSalesChannelId());

        $this->transactionService->savePaymentExtension($transactionId, $order, $payment, $salesChannelContext->getContext());
    }

    /**
     * @param array<mixed> $logData
     */
    private function applyShippingMethod(Session $session, SalesChannelContext $salesChannelContext, array $logData): SalesChannelContext
    {
        $shippingOption = $session->getSelectedShippingOption();
        if (! $shippingOption instanceof ShippingOption) {
            $this->logger->warning('No shipping option could be resolved from the express components session', $logData);

            return $salesChannelContext;
        }

        // the reference of an option is the id of the Shopware shipping method it was built from
        $requestDataBag = new RequestDataBag();
        $requestDataBag->set(SalesChannelContextService::SHIPPING_METHOD_ID, $shippingOption->getReference());

        $logData['shippingMethodId'] = $shippingOption->getReference();
        $this->logger->debug('Express components shipping method applied', $logData);

        return $this->switchContext($requestDataBag, $salesChannelContext);
    }

    /**
     * The client cannot know the order id when it calls this route, so it may leave a placeholder
     * in its urls that is filled in here.
     */
    private function resolveUrl(Request $request, string $parameter, string $orderId): ?string
    {
        $url = (string) $request->get($parameter, '');
        if ($url === '') {
            return null;
        }

        return str_replace(self::ORDER_ID_PLACEHOLDER, $orderId, $url);
    }

    /**
     * Not every completed session names its method: a PayPal express session comes back without
     * one, and Mollie only reports it later through the webhook. The order still needs a payment
     * method to be created with, so the card method stands in until the webhook corrects it.
     */
    private function getPaymentMethodId(Session $session, SalesChannelContext $salesChannelContext): string
    {
        $method = $session->getMethod() ?? PaymentMethod::CREDIT_CARD;

        $paymentMethodId = $this->paymentMethodRepository->getIdByPaymentMethod(
            $method,
            $salesChannelContext->getSalesChannelId(),
            $salesChannelContext->getContext()
        );

        if ($paymentMethodId === null) {
            throw ExpressComponentsException::paymentMethodNotFound($method->value, $salesChannelContext->getSalesChannelId());
        }

        return $paymentMethodId;
    }

    private function restoreContext(string $cartToken, SalesChannelContext $salesChannelContext): SalesChannelContext
    {
        $customer = $salesChannelContext->getCustomer();

        return $this->salesChannelContextService->get(new SalesChannelContextServiceParameters(
            $salesChannelContext->getSalesChannelId(),
            $cartToken,
            originalContext: $salesChannelContext->getContext(),
            customerId: $customer?->getId(),
        ));
    }

    private function switchContext(RequestDataBag $requestDataBag, SalesChannelContext $salesChannelContext): SalesChannelContext
    {
        $customer = $salesChannelContext->getCustomer();
        $contextSwitchResponse = $this->contextSwitchRoute->switchContext($requestDataBag, $salesChannelContext);

        return $this->salesChannelContextService->get(new SalesChannelContextServiceParameters(
            $salesChannelContext->getSalesChannelId(),
            $contextSwitchResponse->getToken(),
            originalContext: $salesChannelContext->getContext(),
            customerId: $customer?->getId(),
        ));
    }
}
