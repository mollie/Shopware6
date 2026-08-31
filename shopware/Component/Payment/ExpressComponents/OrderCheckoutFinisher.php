<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\Gateway\SessionGateway;
use Mollie\Shopware\Component\Mollie\Gateway\SessionGatewayInterface;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\Session;
use Mollie\Shopware\Component\Payment\ExpressComponents\Route\FinishCheckoutResponse;
use Mollie\Shopware\Component\Payment\ExpressMethod\AbstractAccountService;
use Mollie\Shopware\Component\Payment\ExpressMethod\AccountService;
use Mollie\Shopware\Component\Payment\PaymentMethodRepository;
use Mollie\Shopware\Component\Payment\PaymentMethodRepositoryInterface;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractSetPaymentOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\SetPaymentOrderRoute;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * Finishes the express checkout of an order that already exists, which is what the edit order
 * page starts.
 *
 * There is nothing to create: the payment of the completed session is attached to the order and
 * the regular payment handling patches it and moves the transaction on. The shipping method stays
 * as it is, it is part of the order - the addresses however are taken over from the session,
 * because the shopper picked them in the wallet.
 */
final class OrderCheckoutFinisher implements OrderCheckoutFinisherInterface
{
    /**
     * @param EntityRepository<OrderCollection<OrderEntity>> $orderRepository
     */
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: SessionGateway::class)]
        private SessionGatewayInterface $sessionGateway,
        #[Autowire(service: PaymentMethodRepository::class)]
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        #[Autowire(service: AccountService::class)]
        private AbstractAccountService $accountService,
        #[Autowire(service: OrderAddressSynchronizer::class)]
        private OrderAddressSynchronizerInterface $orderAddressSynchronizer,
        #[Autowire(service: PaymentFinalizer::class)]
        private PaymentFinalizerInterface $paymentFinalizer,
        #[Autowire(service: SetPaymentOrderRoute::class)]
        private AbstractSetPaymentOrderRoute $setPaymentOrderRoute,
        #[Autowire(service: 'order.repository')]
        private EntityRepository $orderRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public function finish(string $orderId, SalesChannelContext $salesChannelContext): FinishCheckoutResponse
    {
        $logData = [
            'orderId' => $orderId,
            'salesChannelId' => $salesChannelContext->getSalesChannelId(),
        ];
        $this->logger->info('Start - finish express components checkout for an existing order', $logData);

        $order = $this->getOrder($orderId, $salesChannelContext);
        $logData['orderNumber'] = $order->getOrderNumber();

        // Mollie fills a session asynchronously, so right after the redirect it can still come
        // back without addresses - loadSession waits for them instead of failing the checkout
        $sessionId = $this->getSessionId($order, $salesChannelContext);
        $session = $this->sessionGateway->loadSession($sessionId, $salesChannelContext);
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

        $this->setPayment($orderId, $paymentMethodId, $salesChannelContext);

        // the order is read again, the set payment route added a transaction to it
        $order = $this->getOrder($orderId, $salesChannelContext);

        $redirectUrl = $this->paymentFinalizer->finalize($session, $order, $salesChannelContext);

        $this->logger->info('Finished - finish express components checkout for an existing order', $logData);

        return new FinishCheckoutResponse(
            $session->getId(),
            $salesChannelContext->getToken(),
            $orderId,
            (string) $order->getOrderNumber(),
            $redirectUrl
        );
    }

    private function getSessionId(OrderEntity $order, SalesChannelContext $salesChannelContext): string
    {
        $mode = $this->settingsService->getApiSettings($salesChannelContext->getSalesChannelId())->getMode();

        $sessionId = SessionBuilder::readOrderSessionId($order->getCustomFields() ?? [], $mode);
        if ($sessionId === null) {
            throw ExpressComponentsException::orderSessionIdIsEmpty($order->getId());
        }

        return $sessionId;
    }

    /**
     * Not every completed session names its method: a PayPal express session comes back without
     * one, and Mollie only reports it later through the webhook. The order still needs a payment
     * method to be set with, so the card method stands in until the webhook corrects it.
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
     * The order may already carry the transaction of a failed attempt. Shopware answers a new
     * attempt with a new transaction instead of overwriting the old one, so the same route is
     * used here - it keeps the history and makes the new transaction the primary one.
     */
    private function setPayment(string $orderId, string $paymentMethodId, SalesChannelContext $salesChannelContext): void
    {
        $setPaymentRequest = new Request();
        $setPaymentRequest->request->set('orderId', $orderId);
        $setPaymentRequest->request->set('paymentMethodId', $paymentMethodId);

        $this->setPaymentOrderRoute->setPayment($setPaymentRequest, $salesChannelContext);
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
}
