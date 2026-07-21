<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\PaymentLink\Controller;

use Mollie\Shopware\Component\Mollie\CreatePaymentLink;
use Mollie\Shopware\Component\Mollie\Gateway\PaymentLinkGateway;
use Mollie\Shopware\Component\Mollie\Gateway\PaymentLinkGatewayInterface;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentLink;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use Mollie\Shopware\Component\Payment\Event\ModifyCreatePaymentLinkPayloadEvent;
use Mollie\Shopware\Component\Payment\Event\PaymentLinkCreatedEvent;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\PayloadBuilder;
use Mollie\Shopware\Component\Payment\PayloadBuilderInterface;
use Mollie\Shopware\Component\Payment\PaymentHandlerLocator;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Component\Transaction\MollieOrderTransactionCollection;
use Mollie\Shopware\Component\Transaction\TransactionService;
use Mollie\Shopware\Component\Transaction\TransactionServiceInterface;
use Mollie\Shopware\Entity\PaymentMethod\PaymentMethod as MolliePaymentMethodExtension;
use Mollie\Shopware\Mollie;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\Token\JWTFactoryV2;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterfaceV2;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsController]
#[Route(defaults: ['_routeScope' => ['storefront']])]
final class PaymentLinkController extends StorefrontController
{
    /**
     * Session flag marking a login that was only done so the checkout finish page can be shown for
     * a payment link. {@see \Mollie\Shopware\Component\PaymentLink\Subscriber\TemporaryLoginSubscriber}
     * logs the customer out again right after the finish page has loaded.
     */
    public const TEMPORARY_LOGIN_SESSION_KEY = 'mollie_payments_temporary_login';

    /**
     * @param EntityRepository<OrderCollection<OrderEntity>> $orderRepository
     * @param EntityRepository<OrderTransactionCollection<OrderTransactionEntity>> $orderTransactionRepository
     */
    public function __construct(
        #[Autowire(service: 'order.repository')]
        private EntityRepository $orderRepository,
        #[Autowire(service: 'order_transaction.repository')]
        private EntityRepository $orderTransactionRepository,
        #[Autowire(service: TransactionService::class)]
        private TransactionServiceInterface $transactionService,
        #[Autowire(service: PayloadBuilder::class)]
        private PayloadBuilderInterface $payloadBuilder,
        #[Autowire(service: PaymentLinkGateway::class)]
        private PaymentLinkGatewayInterface $paymentLinkGateway,
        #[Autowire(service: PaymentMethodRoute::class)]
        private AbstractPaymentMethodRoute $paymentMethodRoute,
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: PaymentHandlerLocator::class)]
        private PaymentHandlerLocator $paymentHandlerLocator,
        #[Autowire(service: JWTFactoryV2::class)]
        private TokenFactoryInterfaceV2 $tokenFactory,
        #[Autowire(service: AccountService::class)]
        private AccountService $accountService,
        #[Autowire(service: SalesChannelContextPersister::class)]
        private SalesChannelContextPersister $contextPersister,
        #[Autowire(service: 'request_stack')]
        private RequestStack $requestStack,
        #[Autowire(service: 'event_dispatcher')]
        private EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/mollie/pay/{orderId}', name: 'mollie.pay', methods: ['GET'], options: ['seo' => false])]
    public function pay(string $orderId, SalesChannelContext $context): Response
    {
        $baseContext = $context->getContext();
        $logData = ['orderId' => $orderId];

        $order = $this->loadOrder($orderId, $baseContext);
        if (! $order instanceof OrderEntity) {
            $this->logger->warning('Payment link requested for unknown order', $logData);
            $this->addFlash(self::DANGER, $this->trans('molliePayments.messages.paymentLink.error'));

            return $this->redirectToRoute('frontend.account.order.page');
        }

        $orderNumber = (string) $order->getOrderNumber();
        $salesChannelId = $order->getSalesChannelId();
        $logData['orderNumber'] = $orderNumber;

        $transactionCollection = new MollieOrderTransactionCollection($order->getTransactions());
        $transaction = $transactionCollection->getLatestPayableTransaction();
        if (! $transaction instanceof OrderTransactionEntity) {
            $this->logger->warning('Payment link requested but order has no open transaction', $logData);
            $this->addFlash(self::DANGER, $this->trans('molliePayments.messages.paymentLink.error'));

            return $this->redirectToRoute('frontend.account.order.page');
        }

        $transactionId = $transaction->getId();
        $logData['transactionId'] = $transactionId;

        // An order always has a payment method. Only Mollie payment methods can be paid via a
        // payment link, so bail out for any other method.
        $orderPaymentMethod = $this->getMollieMethod($transaction);
        if ($orderPaymentMethod === null) {
            $this->logger->warning('Payment link requested for an order without a Mollie payment method', $logData);
            $this->addFlash(self::DANGER, $this->trans('molliePayments.messages.paymentLink.error'));

            return $this->redirectToRoute('frontend.account.order.page');
        }

        // Reuse an existing payment link instead of creating a second one. If it already has a
        // settled payment, a new/updated link makes no sense - stop with a flash message.
        $existingPaymentLinkId = $this->getExistingPaymentLinkId($transaction);
        if ($existingPaymentLinkId !== null) {
            $logData['paymentLinkId'] = $existingPaymentLinkId;

            if ($this->isPaymentLinkSettled($existingPaymentLinkId, $orderNumber, $salesChannelId)) {
                $this->logger->info('Payment link already settled, nothing to pay', $logData);
                $this->addFlash(self::INFO, $this->trans('molliePayments.messages.paymentLink.alreadyPaid'));

                return $this->redirectToRoute('frontend.account.order.page');
            }
        }

        $paymentSettings = $this->settingsService->getPaymentSettings($salesChannelId);
        $allowedMethods = $this->resolveAllowedMethods($orderPaymentMethod, $paymentSettings, $context);
        $paymentHandler = $this->resolveSingleHandler($allowedMethods);

        // Like a regular Shopware checkout, finalize has to run on return (persists the payment
        // details, simulates the webhook in dev mode). We build the finalize URL - including a
        // freshly issued token - here and store it on the transaction. The regular return route
        // (frontend.mollie.payment, the link's redirect URL) then forwards to it. The token is
        // never sent to Mollie: its redirect URL has a length limit that truncates the long JWT.
        $finalizeUrl = $this->generateFinalizeUrl($transaction, $order);

        $transactionData = $this->transactionService->findById($transactionId, $baseContext);

        // The customer who opens the pay link from a mail is usually not logged in, so the checkout
        // finish page (which requires the order to belong to the logged-in customer) would redirect
        // to the cart. Log the order's customer into the session; CustomerLoginEvent then persists
        // the new context token via the storefront subscriber, so the return request is logged in.
        $customerId = $transactionData->getCustomer()->getId();
        $this->loginOrderCustomer($customerId, $context);

        $createPaymentLink = $this->payloadBuilder->buildPaymentLink($transactionData, $allowedMethods, $paymentHandler, $baseContext);

        $modifyEvent = new ModifyCreatePaymentLinkPayloadEvent($createPaymentLink, $baseContext);
        /** @var ModifyCreatePaymentLinkPayloadEvent $modifyEvent */
        $modifyEvent = $this->eventDispatcher->dispatch($modifyEvent);
        $createPaymentLink = $modifyEvent->getPaymentLink();

        $paymentLink = $this->createOrUpdatePaymentLink($createPaymentLink, $orderNumber, $salesChannelId, $existingPaymentLinkId);
        $paymentLinkId = $paymentLink->getId();
        $paymentLinkUrl = $paymentLink->getUrl();
        $logData['paymentLinkId'] = $paymentLinkId;

        $this->storePaymentLinkData($transaction, $paymentLinkId, $finalizeUrl, $baseContext);

        // Mirror the checkout: this creates the pending subscription for a subscription order (the
        // paid webhook confirms it afterwards). PaymentLinkCreatedEvent shares the base event with
        // the checkout's PaymentCreatedEvent, so the subscription subscriber handles both.
        $paymentLinkCreatedEvent = new PaymentLinkCreatedEvent($paymentLinkUrl, $transactionData, $baseContext);
        $this->eventDispatcher->dispatch($paymentLinkCreatedEvent);

        $this->logger->info('Payment link created, redirecting customer', $logData);

        return new RedirectResponse($paymentLinkUrl);
    }

    private function loadOrder(string $orderId, Context $context): ?OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions.stateMachineState');
        $criteria->addAssociation('transactions.paymentMethod');

        $order = $this->orderRepository->search($criteria, $context)->first();

        return $order instanceof OrderEntity ? $order : null;
    }

    /**
     * The Mollie payment method of the order's transaction, or null when it is not a Mollie method.
     */
    private function getMollieMethod(OrderTransactionEntity $transaction): ?PaymentMethod
    {
        $paymentMethod = $transaction->getPaymentMethod();
        if ($paymentMethod === null) {
            return null;
        }

        $extension = $paymentMethod->getExtension(Mollie::EXTENSION);

        return $extension instanceof MolliePaymentMethodExtension ? $extension->getPaymentMethod() : null;
    }

    /**
     * By default the link only offers the payment method assigned to the order. When the plugin
     * setting allows it, the customer may instead pick from all methods available for the order -
     * loaded via the regular payment method route so our removers apply, exactly like the checkout.
     *
     * @return string[]
     */
    private function resolveAllowedMethods(PaymentMethod $orderPaymentMethod, PaymentSettings $paymentSettings, SalesChannelContext $context): array
    {
        if (! $paymentSettings->isPaymentLinkMethodSelectionAllowed()) {
            return $this->orderMethodAsAllowedMethods($orderPaymentMethod);
        }

        $paymentMethodRequest = new Request(['onlyAvailable' => true]);
        $response = $this->paymentMethodRoute->load($paymentMethodRequest, $context, new Criteria());

        $methods = [];
        foreach ($response->getPaymentMethods() as $availablePaymentMethod) {
            $extension = $availablePaymentMethod->getExtension(Mollie::EXTENSION);
            if (! $extension instanceof MolliePaymentMethodExtension) {
                continue;
            }

            // Payment links accept fewer methods than the Payments API in "allowedMethods"; sending
            // an unsupported one makes Mollie reject the whole request, so skip those here.
            $mollieMethod = $extension->getPaymentMethod();
            if ($mollieMethod->isSupportedForPaymentLink()) {
                $methods[] = $mollieMethod->value;
            }
        }

        // Several Shopware methods can map to the same Mollie method (e.g. the Orders and Payments
        // API variants of PayPal/Klarna), so drop the duplicates before sending them.
        $methods = array_values(array_unique($methods));

        // The removers/filter may strip every method (e.g. none available or link-supported); fall
        // back to the method the order was placed with so the link still offers a payable option.
        if (count($methods) === 0) {
            return $this->orderMethodAsAllowedMethods($orderPaymentMethod);
        }

        return $methods;
    }

    /**
     * The order's own method as the sole allowed method - but only when payment links support it.
     * Otherwise no restriction is sent (empty), so Mollie offers all methods of the profile instead
     * of rejecting the request over an unsupported method.
     *
     * @return string[]
     */
    private function orderMethodAsAllowedMethods(PaymentMethod $orderPaymentMethod): array
    {
        return $orderPaymentMethod->isSupportedForPaymentLink() ? [$orderPaymentMethod->value] : [];
    }

    /**
     * With exactly one allowed method the link targets a single payment method, so we resolve its
     * handler to let the payload builder apply the method's payment-specific parameters.
     *
     * @param string[] $allowedMethods
     */
    private function resolveSingleHandler(array $allowedMethods): ?AbstractMolliePaymentHandler
    {
        if (count($allowedMethods) !== 1) {
            return null;
        }

        return $this->paymentHandlerLocator->findByPaymentMethod($allowedMethods[0]);
    }

    private function getExistingPaymentLinkId(OrderTransactionEntity $transaction): ?string
    {
        $mollieExtension = $transaction->getExtension(Mollie::EXTENSION);

        return $mollieExtension instanceof Payment ? $mollieExtension->getPaymentLinkId() : null;
    }

    private function createOrUpdatePaymentLink(CreatePaymentLink $createPaymentLink, string $orderNumber, string $salesChannelId, ?string $existingPaymentLinkId): PaymentLink
    {
        if ($existingPaymentLinkId !== null) {
            return $this->paymentLinkGateway->updatePaymentLink($existingPaymentLinkId, $createPaymentLink, $orderNumber, $salesChannelId);
        }

        return $this->paymentLinkGateway->createPaymentLink($createPaymentLink, $orderNumber, $salesChannelId);
    }

    /**
     * A payment link is "settled" once one of its payments is paid, authorized or refund-related -
     * i.e. it was actually paid. Open/pending payments do not count (a new link is still valid).
     */
    private function isPaymentLinkSettled(string $paymentLinkId, string $orderNumber, string $salesChannelId): bool
    {
        $payments = $this->paymentLinkGateway->getPaymentLinkPayments($paymentLinkId, $orderNumber, $salesChannelId);

        foreach ($payments as $payment) {
            $status = $payment->getStatus();
            if ($status === PaymentStatus::PAID || $status === PaymentStatus::AUTHORIZED || $status->isRefundRelated()) {
                return true;
            }
        }

        return false;
    }

    private function loginOrderCustomer(string $customerId, SalesChannelContext $context): void
    {
        try {
            $newToken = $this->accountService->loginById($customerId, $context);

            // AccountService only builds the logged-in context in memory and switches the session to
            // its new token; it never persists the customer under that token. Without persisting it
            // the return request rebuilds an anonymous context from the token, so the finish page
            // (which loads the order for the logged-in customer) redirects to the cart. Persist the
            // customer under the new token so the return request is logged in.
            $this->contextPersister->save(
                $newToken,
                [SalesChannelContextService::CUSTOMER_ID => $customerId],
                $context->getSalesChannelId(),
                $customerId
            );

            // Mark this as a temporary login: the customer is logged out again right after the
            // finish page loaded, so opening the link does not leave a stranger logged in.
            $this->requestStack->getSession()->set(self::TEMPORARY_LOGIN_SESSION_KEY, true);
        } catch (\Throwable $exception) {
            // A failed login (e.g. inactive customer) must not block the payment; the finish page
            // will then fall back to its cart redirect, but the payment itself still works.
            $this->logger->warning('Could not log in the order customer for the payment link', [
                'customerId' => $customerId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Builds the Shopware finalize URL (incl. a freshly issued payment token) the customer is sent
     * back to after paying. The token is a JWT created with Shopware's own token factory so the
     * core finalize controller accepts it, and it carries the finish/error redirect targets.
     */
    private function generateFinalizeUrl(OrderTransactionEntity $transaction, OrderEntity $order): string
    {
        $orderId = $order->getId();

        $tokenStruct = new TokenStruct(
            null,
            null,
            $transaction->getPaymentMethodId(),
            $transaction->getId(),
            $this->generateUrl('frontend.checkout.finish.page', ['orderId' => $orderId]),
            null,
            $this->generateUrl('frontend.account.edit-order.page', ['orderId' => $orderId]),
        );

        $token = $this->tokenFactory->generateToken($tokenStruct);

        return $this->generateUrl('payment.finalize.transaction', ['_sw_payment_token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function storePaymentLinkData(OrderTransactionEntity $transaction, string $paymentLinkId, string $finalizeUrl, Context $context): void
    {
        $customFields = $transaction->getCustomFields() ?? [];
        $mollieData = $customFields[Mollie::EXTENSION] ?? [];
        $mollieData['paymentLinkId'] = $paymentLinkId;
        $mollieData['finalizeUrl'] = $finalizeUrl;
        $customFields[Mollie::EXTENSION] = $mollieData;

        $this->orderTransactionRepository->upsert([
            [
                'id' => $transaction->getId(),
                'customFields' => $customFields,
            ],
        ], $context);
    }
}
