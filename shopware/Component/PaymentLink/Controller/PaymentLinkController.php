<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\PaymentLink\Controller;

use Mollie\Shopware\Component\Mollie\Gateway\PaymentLinkGateway;
use Mollie\Shopware\Component\Mollie\Gateway\PaymentLinkGatewayInterface;
use Mollie\Shopware\Component\PaymentLink\PaymentLinkBuilder;
use Mollie\Shopware\Component\PaymentLink\PaymentLinkBuilderInterface;
use Mollie\Shopware\Component\Transaction\TransactionService;
use Mollie\Shopware\Component\Transaction\TransactionServiceInterface;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['storefront'], 'csrf_protected' => false])]
final class PaymentLinkController extends StorefrontController
{
    /**
     * @param EntityRepository<OrderTransactionCollection<OrderTransactionEntity>> $orderTransactionRepository
     */
    public function __construct(
        #[Autowire(service: TransactionService::class)]
        private readonly TransactionServiceInterface $transactionService,
        #[Autowire(service: PaymentLinkBuilder::class)]
        private readonly PaymentLinkBuilderInterface $paymentLinkBuilder,
        #[Autowire(service: PaymentLinkGateway::class)]
        private readonly PaymentLinkGatewayInterface $paymentLinkGateway,
        #[Autowire(service: 'order_transaction.repository')]
        private readonly EntityRepository $orderTransactionRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/mollie/pay/{transactionId}', name: 'frontend.mollie.payment-link', methods: ['GET'], options: ['seo' => false])]
    public function pay(string $transactionId, SalesChannelContext $salesChannelContext): Response
    {
        $transactionId = strtolower($transactionId);
        $context = $salesChannelContext->getContext();

        try {
            $transactionData = $this->transactionService->findById($transactionId, $context);
        } catch (\Throwable $exception) {
            $this->logger->warning('Payment link requested for unknown transaction', [
                'transactionId' => $transactionId,
                'error' => $exception->getMessage(),
            ]);
            throw $this->createNotFoundException();
        }

        if (! $this->isTransactionPayable($transactionData->getTransaction())) {
            $this->addFlash(self::INFO, $this->trans('molliePayments.messages.paymentLink.alreadyPaid'));

            return $this->redirectToRoute('frontend.home.page');
        }

        $order = $transactionData->getOrder();
        $orderNumber = (string) $order->getOrderNumber();
        $salesChannelId = $order->getSalesChannelId();

        $createPaymentLink = $this->paymentLinkBuilder->build($transactionData);
        $paymentLink = $this->paymentLinkGateway->createPaymentLink($createPaymentLink, $orderNumber, $salesChannelId);

        // Store the payment link id so the regular webhook and return routes can resolve the payment
        // once the customer paid (a payment link only creates a Mollie payment on payment).
        $this->orderTransactionRepository->upsert([
            [
                'id' => $transactionId,
                'customFields' => [
                    Mollie::EXTENSION => ['paymentLinkId' => $paymentLink->getId()],
                ],
            ],
        ], $context);

        $this->logger->info('Payment link created and customer redirected', [
            'transactionId' => $transactionId,
            'orderNumber' => $orderNumber,
            'paymentLinkId' => $paymentLink->getId(),
        ]);

        return $this->redirect($paymentLink->getPaymentLinkUrl());
    }

    private function isTransactionPayable(OrderTransactionEntity $transaction): bool
    {
        $state = $transaction->getStateMachineState()?->getTechnicalName() ?? '';

        return ! in_array($state, [
            OrderTransactionStates::STATE_PAID,
            OrderTransactionStates::STATE_PARTIALLY_PAID,
            OrderTransactionStates::STATE_AUTHORIZED,
            OrderTransactionStates::STATE_REFUNDED,
            OrderTransactionStates::STATE_PARTIALLY_REFUNDED,
        ], true);
    }
}
