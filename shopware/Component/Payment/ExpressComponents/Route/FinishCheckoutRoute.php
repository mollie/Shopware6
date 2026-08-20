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
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractHandlePaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\HandlePaymentMethodRoute;
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
    public const FINISH_URL_PARAMETER = 'finishUrl';
    public const ERROR_URL_PARAMETER = 'errorUrl';

    /**
     * Placeholder a client may use in its own finish and error url. The order does not exist yet
     * when the client calls this route, so it cannot build the id into the url itself.
     */
    public const ORDER_ID_PLACEHOLDER = '{orderId}';

    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: SessionGateway::class)]
        private SessionGatewayInterface $sessionGateway,
        #[Autowire(service: MollieGateway::class)]
        private MollieGatewayInterface $mollieGateway,
        #[Autowire(service: AccountService::class)]
        private AbstractAccountService $accountService,
        #[Autowire(service: PaymentMethodRepository::class)]
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        #[Autowire(service: TransactionService::class)]
        private TransactionServiceInterface $transactionService,
        #[Autowire(service: CartOrderRoute::class)]
        private AbstractCartOrderRoute $cartOrderRoute,
        #[Autowire(service: HandlePaymentMethodRoute::class)]
        private AbstractHandlePaymentMethodRoute $handlePaymentMethodRoute,
        #[Autowire(service: RouteBuilder::class)]
        private RouteBuilderInterface $routeBuilder,
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

        $storedSession = $cart->getExtension(SessionBuilder::CART_EXTENSION);
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

        $orderContext = $this->accountService->loginOrCreateAccount($paymentMethodId, $billingAddress, $shippingAddress, $cartContext);
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

        $this->attachPayment($session, $order, $orderContext, $logData);
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
    private function attachPayment(Session $session, OrderEntity $order, SalesChannelContext $salesChannelContext, array $logData): void
    {
        $paymentId = $session->getPaymentId();
        if ($paymentId === '') {
            $this->logger->error('Express components session carries no payment id', $logData);

            return;
        }

        $transaction = $order->getTransactions()?->last();
        if (! $transaction instanceof OrderTransactionEntity) {
            $this->logger->error('Express components order has no transaction', $logData);

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

    private function getPaymentMethodId(Session $session, SalesChannelContext $salesChannelContext): string
    {
        $method = $session->getMethod();
        if (! $method instanceof PaymentMethod) {
            throw ExpressComponentsException::paymentMethodNotFound('', $salesChannelContext->getSalesChannelId());
        }

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
