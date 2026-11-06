<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents\Route;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Payment\ExpressComponents\ExpressComponentsException;
use Mollie\Shopware\Component\Payment\ExpressComponents\SessionPaymentRepository;
use Mollie\Shopware\Component\Payment\ExpressComponents\SessionPaymentRepositoryInterface;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\PayloadBuilder;
use Mollie\Shopware\Component\Payment\PayloadBuilderInterface;
use Mollie\Shopware\Component\Payment\PaymentHandlerLocator;
use Mollie\Shopware\Component\Payment\Route\AbstractWebhookRoute;
use Mollie\Shopware\Component\Payment\Route\WebhookResponse;
use Mollie\Shopware\Component\Payment\Route\WebhookRoute;
use Mollie\Shopware\Component\Transaction\TransactionDataStruct;
use Mollie\Shopware\Component\Transaction\TransactionService;
use Mollie\Shopware\Component\Transaction\TransactionServiceInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Mollie calls the webhook of a session payment with the payment id only. Once the shopper is back
 * and the order transaction exists, the payment is attached to it and its webhook is moved to the
 * transaction. The finalize is left to Shopware, which the waiting shopper is sent through.
 */
#[AsController]
#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => false, 'auth_enabled' => false])]
final class SessionWebhookRoute extends AbstractSessionWebhookRoute
{
    public function __construct(
        #[Autowire(service: SessionPaymentRepository::class)]
        private readonly SessionPaymentRepositoryInterface $sessionPaymentRepository,
        #[Autowire(service: TransactionService::class)]
        private readonly TransactionServiceInterface $transactionService,
        #[Autowire(service: MollieGateway::class)]
        private readonly MollieGatewayInterface $mollieGateway,
        #[Autowire(service: PayloadBuilder::class)]
        private readonly PayloadBuilderInterface $payloadBuilder,
        private readonly PaymentHandlerLocator $paymentHandlerLocator,
        #[Autowire(service: WebhookRoute::class)]
        private readonly AbstractWebhookRoute $webhookRoute,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): AbstractSessionWebhookRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/api/mollie/session/webhook/{webhookId}', name: 'api.mollie.webhook.session', methods: ['GET', 'POST'])]
    public function notify(string $webhookId, Request $request, Context $context): WebhookResponse
    {
        $paymentId = (string) $request->request->get('id', $request->query->get('id', ''));
        $logData = [
            'webhookId' => $webhookId,
            'paymentId' => $paymentId,
        ];
        $this->logger->info('Express components session webhook received', $logData);

        $sessionPayment = $this->sessionPaymentRepository->findById($webhookId, $context);
        if ($sessionPayment === null) {
            $this->logger->error('Express components session webhook for an unknown session', $logData);
            throw ExpressComponentsException::sessionPaymentNotFound($webhookId);
        }
        $logData['sessionId'] = $sessionPayment->getSessionId();

        $storedPaymentId = $sessionPayment->getPaymentId();
        if ($storedPaymentId !== null && $storedPaymentId !== $paymentId) {
            $this->logger->warning('Express components session received a second payment id', $logData + ['storedPaymentId' => $storedPaymentId]);
        }
        // read before the payment is stored, storing it moves the update time
        $shopperStoppedWaiting = $sessionPayment->getTransactionId() !== null && $this->sessionPaymentRepository->hasShopperStoppedWaiting($webhookId, $context);
        $this->sessionPaymentRepository->assignPayment($webhookId, $paymentId, $context);

        $transactionId = $sessionPayment->getTransactionId();
        if ($transactionId === null) {
            $this->logger->info('Express components session webhook arrived before the order transaction exists', $logData);
            throw ExpressComponentsException::sessionTransactionMissing($webhookId);
        }
        $logData['transactionId'] = $transactionId;

        $transactionData = $this->transactionService->findById($transactionId, $context);
        $order = $transactionData->getOrder();
        $orderNumber = (string) $order->getOrderNumber();
        $salesChannelId = $order->getSalesChannelId();
        $logData['orderNumber'] = $orderNumber;

        $payment = $this->mollieGateway->getPayment($paymentId, $orderNumber, $salesChannelId);
        $paymentHandler = $this->getPaymentHandler($payment);

        $this->transactionService->savePaymentExtension($transactionId, $order, $payment, $context, expressCheckout: true);
        $this->updatePayment($transactionData, $paymentHandler, $payment, $context);
        $this->logger->info('Express components payment attached to the order transaction', $logData);

        if ($shopperStoppedWaiting) {
            $this->logger->info('Express components shopper stopped waiting for the payment, the webhook sets the status', $logData);
            $this->webhookRoute->notify($transactionId, $context);
        }

        return new WebhookResponse($payment);
    }

    private function getPaymentHandler(Payment $payment): AbstractMolliePaymentHandler
    {
        $method = $payment->getMethod();
        if ($method === null) {
            throw ExpressComponentsException::sessionPaymentWithoutMethod($payment->getId());
        }

        $paymentHandler = $this->paymentHandlerLocator->findByPaymentMethod($method->value);
        if ($paymentHandler === null) {
            throw ExpressComponentsException::paymentHandlerNotFound($method->value);
        }

        return $paymentHandler;
    }

    /**
     * Gives the payment what only Shopware knows: the description with the order number, the
     * metadata and the webhook url of the transaction. Mollie refuses to change the redirect url of
     * a finalized payment, so it is left out.
     */
    private function updatePayment(TransactionDataStruct $transactionData, AbstractMolliePaymentHandler $paymentHandler, Payment $payment, Context $context): void
    {
        $order = $transactionData->getOrder();
        $createPayment = $this->payloadBuilder->buildPayment($transactionData, $paymentHandler, new RequestDataBag(), $context);
        $createPayment->omitRedirectUrl();

        $this->mollieGateway->updatePayment($payment->getId(), $createPayment, (string) $order->getOrderNumber(), $order->getSalesChannelId());
    }
}
