<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Controller;

use Mollie\Shopware\Component\Payment\Route\AbstractReturnRoute;
use Mollie\Shopware\Component\Payment\Route\AbstractWebhookRoute;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Payment\Controller\PaymentController as ShopwarePaymentController;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PaymentController extends AbstractController
{
    public function __construct(private AbstractReturnRoute $returnRoute,
        private AbstractWebhookRoute $webhookRoute,
        private LoggerInterface $logger,
    ) {
    }

    public function return(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $transactionId = $request->get('transactionId');

        $this->logger->info('Returning from Payment Provider', [
            'transactionId' => $transactionId,
            'salesChannel' => $salesChannelContext->getSalesChannel()->getName()
        ]);
        $response = $this->returnRoute->return($request, $salesChannelContext);
        $paymentStatus = $response->getPaymentStatus();

        if ($paymentStatus->isFailed()) {
            // TODO mollie failure mode
        }
        $query = parse_url($response->getFinalizeUrl(), PHP_URL_QUERY);
        $queryParameters = [];
        parse_str($query, $queryParameters);
        $this->logger->info('Finalize transaction', [
            'transactionId' => $transactionId,
        ]);
        $controller = sprintf('%s::%s', ShopwarePaymentController::class, 'finalizeTransaction');

        return $this->forward($controller, [], $queryParameters);
    }

    public function webhook(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $transactionId = $request->get('transactionId');
        try {
            $this->logger->info('Webhook received', [
                'transactionId' => $transactionId,
                'salesChannel' => $salesChannelContext->getSalesChannel()->getName()
            ]);
            $response = $this->webhookRoute->notify($request, $salesChannelContext->getContext());

            return new JsonResponse($response->getObject());
        } catch (ShopwareHttpException $exception) {
            $this->logger->warning(
                'Webhook request failed with warning',
                [
                    'transactionId' => $transactionId,
                    'salesChannel' => $salesChannelContext->getSalesChannel()->getName(),
                    'message' => $exception->getMessage(),
                ]
            );

            return new JsonResponse(['success' => false, 'error' => $exception->getMessage()], $exception->getStatusCode());
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Webhook request failed',
                [
                    'transactionId' => $transactionId,
                    'salesChannel' => $salesChannelContext->getSalesChannel()->getName(),
                    'message' => $exception->getMessage(),
                ]
            );

            return new JsonResponse(['success' => false, 'error' => $exception->getMessage()], 422);
        }
    }
}
