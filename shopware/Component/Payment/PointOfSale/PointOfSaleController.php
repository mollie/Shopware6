<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\PointOfSale;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use Mollie\Shopware\Component\Payment\PointOfSale\Route\StoreTerminalRoute;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
final class PointOfSaleController extends StorefrontController
{
    public function __construct(
        #[Autowire(service: MollieGateway::class)]
        private MollieGatewayInterface $mollieGateway,
        private StoreTerminalRoute $storeTerminalRoute,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/mollie/pos/checkout', methods: ['GET'], name: 'frontend.mollie.pos.checkout', options: ['seo' => false])]
    public function checkout(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $salesChannel = $salesChannelContext->getSalesChannel();

        $logParameters = [
            'transactionId' => $request->get('transactionId'),
            'orderNumber' => $request->get('orderNumber'),
            'salesChannelId' => $salesChannel->getId(),
            'salesChannelName' => $salesChannel->getName(),
        ];

        $this->logger->info('Opened Terminal route', $logParameters);

        return $this->renderStorefront('@Storefront/mollie/pos/checkout.html.twig', $request->query->all());
    }

    #[Route(path: '/mollie/pos/{transactionId}/{paymentId}/status', methods: ['GET'], name: 'frontend.mollie.pos.checkout_status', options: ['seo' => false])]
    public function checkStatus(string $transactionId, string $paymentId, SalesChannelContext $salesChannelContext): Response
    {
        $salesChannel = $salesChannelContext->getSalesChannel();

        $logParameters = [
            'orderNumber' => $transactionId,
            'paymentId' => $paymentId,
            'salesChannelId' => $salesChannel->getId(),
            'salesChannelName' => $salesChannel->getName(),
        ];
        $this->logger->debug('Check payment status from terminal', $logParameters);

        $payment = $this->mollieGateway->getPaymentByTransactionId($transactionId, $salesChannelContext->getContext());
        $ready = $payment->getStatus() !== PaymentStatus::OPEN;

        return new JsonResponse([
            'ready' => $ready,
            'redirectUrl' => $payment->getFinalizeUrl(),
            'success' => true,
        ]);
    }

    #[Route(path: '/mollie/pos/store-terminal/{customerId}/{terminalId}',name: 'frontend.mollie.pos.storeTerminal',options: ['seo' => false])]
    public function storeTerminal(string $customerId,string $terminalId,SalesChannelContext $salesChannelContext): JsonResponse
    {
        $response = $this->storeTerminalRoute->storeTerminal($customerId, $terminalId, $salesChannelContext);

        return new JsonResponse($response->getObject()->getVars());
    }
}
