<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Controller;

use Mollie\Shopware\Component\Payment\Route\ReturnRoute;
use Mollie\shopware\Component\Payment\Route\WebhookRoute;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

final class PaymentController extends AbstractController
{
    public function __construct(private ReturnRoute $returnRoute,
                                private WebhookRoute $webhookRoute,
                                private LoggerInterface $logger,
    ) {
    }

    public function return(string $transactionId, SalesChannelContext $salesChannelContext): Response
    {
        $this->logger->info('Returning from Payment Provider', [
            'transactionId' => $transactionId,
            'salesChannel' => $salesChannelContext->getSalesChannel()->getName()
        ]);
        $response = $this->returnRoute->return($transactionId, $salesChannelContext);
        $paymentStatus = $response->getPaymentStatus();

        if ($paymentStatus->isFailed()) {
            //TODO mollie failure mode
        }
        $query = parse_url($response->getFinalizeUrl(), PHP_URL_QUERY);
        $queryParameters = [];
        parse_str($query, $queryParameters);
        $this->logger->info('Finalize transaction', [
            'transactionId' => $transactionId,
        ]);

        return $this->forward('\Shopware\Core\Checkout\Payment\Controller\PaymentController::finalizeTransaction', [], $queryParameters);
    }

    public function webhook(string $transactionId, SalesChannelContext $salesChannelContext): Response
    {
    }
}
