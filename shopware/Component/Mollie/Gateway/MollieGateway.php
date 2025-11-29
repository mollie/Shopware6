<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use GuzzleHttp\Exception\ClientException;
use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Repository\OrderTransactionRepositoryInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
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
        if ($transaction === null) {
            throw new \Exception('Transaction ' . $transactionId . ' not found in Shopware');
        }
        $transactionOrder = $transaction->getOrder();
        if (! $transactionOrder instanceof OrderEntity) {
            throw new \Exception('Transaction ' . $transactionId . ' without order');
        }
        /** @var ?Payment $mollieTransaction */
        $mollieTransaction = $transaction->getExtension(Mollie::EXTENSION);

        if ($mollieTransaction === null) {
            $mollieTransaction = $this->repairLegacyTransaction($transaction,$transactionOrder, $context);
            if ($mollieTransaction === null) {
                throw new \Exception('Transaction was not created by mollie');
            }
        }

        $payment = $this->getPayment($mollieTransaction->getId(),$transactionOrder->getSalesChannelId());
        $payment->setFinalizeUrl($mollieTransaction->getFinalizeUrl());
        $payment->setShopwareTransaction($transaction);

        return $payment;
    }

    public function getPayment(string $molliePaymentId, string $salesChannelId): Payment
    {
        try {
            $client = $this->clientFactory->create($salesChannelId);
            $response = $client->get('payments/' . $molliePaymentId);
            $body = json_decode($response->getBody()->getContents(), true);

            return Payment::createFromClientResponse($body);
        } catch (ClientException $exception) {
            throw $this->convertException($exception);
        }
    }

    public function createPayment(CreatePayment $molliePayment, string $salesChannelId): Payment
    {
        try {
            $client = $this->clientFactory->create($salesChannelId);
            $formParams = $molliePayment->toArray();
            $this->logger->debug('Create payment via Payments API', [
                'formParams' => $formParams,
            ]);
            $response = $client->post('payments', [
                'form_params' => $molliePayment->toArray(),
            ]);
            $body = json_decode($response->getBody()->getContents(), true);

            return Payment::createFromClientResponse($body);
        } catch (ClientException $exception) {
            throw $this->convertException($exception);
        }
    }

    private function getPaymentByMollieOrderId(string $mollieOrderId, string $salesChannelId): Payment
    {
        try {
            $client = $this->clientFactory->create($salesChannelId);
            $response = $client->get('orders/' . $mollieOrderId, [
                'query' => [
                    'embed' => 'payments',
                ]
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            $paymentsBody = $body['_embedded']['payments'][0] ?? [];

            return Payment::createFromClientResponse($paymentsBody);
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

    private function repairLegacyTransaction(OrderTransactionEntity $transaction, OrderEntity $order, Context $context): ?Payment
    {
        $salesChannelId = $order->getSalesChannelId();
        $customFields = $order->getCustomFields()[Mollie::EXTENSION] ?? null;
        if ($customFields === null) {
            return null;
        }
        $mollieOrderId = $customFields['order_id'] ?? null;
        $returnUrl = $customFields['transactionReturnUrl'] ?? null;
        if ($mollieOrderId === null || $returnUrl === null) {
            return null;
        }

        $payment = $this->getPaymentByMollieOrderId($mollieOrderId, $salesChannelId);
        $payment->setFinalizeUrl($returnUrl);
        $this->orderTransactionRepository->savePaymentExtension($transaction, $payment, $context);

        return $payment;
    }
}
