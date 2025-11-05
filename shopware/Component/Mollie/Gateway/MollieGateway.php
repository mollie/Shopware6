<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use GuzzleHttp\Exception\ClientException;
use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Repository\OrderTransactionRepositoryInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;

final class MollieGateway implements MollieGatewayInterface
{
    public function __construct(private ClientFactoryInterface $clientFactory,
                                private OrderTransactionRepositoryInterface $orderTransactionRepository,
                                private LoggerInterface $logger)
    {
    }

    public function getPaymentByTransactionId(string $transactionId, Context $context): Payment
    {
        $transaction = $this->orderTransactionRepository->findById($transactionId, $context);
        /** @var ?Payment $mollieTransaction */
        $mollieTransaction = $transaction->getExtension(Mollie::EXTENSION);

        if ($mollieTransaction === null) {
            throw new \Exception('Transaction was not created by mollie');
        }

        $payment = $this->getPayment($mollieTransaction->getId(), $transaction->getOrder()->getSalesChannelId());
        $payment->setFinalizeUrl($mollieTransaction->getFinalizeUrl());
        $payment->setShopwareTransaction($transaction);

        return $payment;
    }

    public function getPayment(string $molliePaymentId, string $salesChannelId): Payment
    {
        $client = $this->clientFactory->create($salesChannelId);

        try {
            $response = $client->get('payments/' . $molliePaymentId);

            return Payment::createFromClientResponse($response);
        } catch (ClientException $exception) {
            throw $this->convertException($exception);
        }
    }

    public function createPayment(CreatePayment $molliePayment, string $salesChannelId): Payment
    {
        $client = $this->clientFactory->create($salesChannelId);
        $formParams = $molliePayment->toArray();
        $this->logger->debug('Create payment via Payments API', [
            'formParams' => $formParams,
        ]);
        try {
            $response = $client->post('payments', [
                'form_params' => $molliePayment->toArray(),
            ]);

            return Payment::createFromClientResponse($response);
        } catch (ClientException $exception) {
            throw $this->convertException($exception);
        }
    }

    private function convertException(ClientException $exception): ApiException
    {
        $body = json_decode($exception->getResponse()->getBody()->getContents(), true);
        $this->logger->error('Failed to create payment', [
            'title' => $body['title'] ?? '',
            'error' => $body['detail'] ?? '',
            'field' => $body['field'] ?? '',
        ]);

        return new ApiException($exception->getCode(), $body['title'] ?? '', $body['detail'] ?? '', $body['field'] ?? '');
    }
}
