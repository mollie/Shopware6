<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\Gateway\SessionGateway;
use Mollie\Shopware\Component\Mollie\Gateway\SessionGatewayInterface;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\Session;
use Mollie\Shopware\Component\Mollie\ShippingOption;
use Mollie\Shopware\Component\Payment\ExpressComponents\Route\FinishCheckoutResponse;
use Mollie\Shopware\Component\Payment\ExpressMethod\AbstractAccountService;
use Mollie\Shopware\Component\Payment\ExpressMethod\AccountService;
use Mollie\Shopware\Component\Payment\PaymentMethodRepository;
use Mollie\Shopware\Component\Payment\PaymentMethodRepositoryInterface;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartOrderRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartOrderRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Turns the cart the shopper paid for into an order.
 *
 * The customer data comes out of the Mollie session like in PayPal express, the order is then
 * created from the cart like in Apple Pay direct.
 */
final class CartCheckoutFinisher implements CartCheckoutFinisherInterface
{
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: SessionGateway::class)]
        private SessionGatewayInterface $sessionGateway,
        #[Autowire(service: PaymentMethodRepository::class)]
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        #[Autowire(service: AccountService::class)]
        private AbstractAccountService $accountService,
        #[Autowire(service: PaymentFinalizer::class)]
        private PaymentFinalizerInterface $paymentFinalizer,
        #[Autowire(service: CartOrderRoute::class)]
        private AbstractCartOrderRoute $cartOrderRoute,
        #[Autowire(service: SalesChannelContextService::class)]
        private SalesChannelContextServiceInterface $salesChannelContextService,
        #[Autowire(service: ContextSwitchRoute::class)]
        private AbstractContextSwitchRoute $contextSwitchRoute,
        private CartService $cartService,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public function finish(string $cartToken, SalesChannelContext $salesChannelContext): FinishCheckoutResponse
    {
        $logData = [
            'cartToken' => $cartToken,
            'salesChannelId' => $salesChannelContext->getSalesChannelId(),
        ];
        $this->logger->info('Start - finish express components checkout', $logData);

        // Mollie redirects the shopper back without the cart he started from, so the context
        // of that cart is restored from the token that was handed to the redirect url
        $cartContext = $this->restoreContext($cartToken, $salesChannelContext);

        // Mollie fills a session asynchronously, so right after the redirect it can still come
        // back without addresses - loadSession waits for them instead of failing the checkout
        $sessionId = $this->getSessionId($cartToken, $cartContext);
        $session = $this->sessionGateway->loadSession($sessionId, $cartContext);
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

        $redirectUrl = $this->paymentFinalizer->finalize($session, $order, $orderContext);

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
     * A cart cannot be looked up by the Mollie session id: the session lives in the cart payload,
     * which is stored as a blob. So the session is read back out of the cart the token names.
     */
    private function getSessionId(string $cartToken, SalesChannelContext $cartContext): string
    {
        $cart = $this->cartService->getCart($cartToken, $cartContext);

        $mode = $this->settingsService->getApiSettings($cartContext->getSalesChannelId())->getMode();
        $storedSession = $cart->getExtension(SessionBuilder::cartExtensionKey($mode));
        if (! $storedSession instanceof Session) {
            throw ExpressComponentsException::cartSessionIdIsEmpty($cartToken);
        }

        return $storedSession->getId();
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
