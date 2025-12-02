<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use GuzzleHttp\Exception\ClientException;
use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Transaction\TransactionDataLoader;
use Mollie\Shopware\Component\Transaction\TransactionDataLoaderInterface;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Repository\OrderTransactionRepository;
use Mollie\Shopware\Repository\OrderTransactionRepositoryInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class MollieGateway implements MollieGatewayInterface
{
    public function __construct(
        #[Autowire(service: ClientFactory::class)]
        private ClientFactoryInterface $clientFactory,
        #[Autowire(service: TransactionDataLoader::class)]
        private TransactionDataLoaderInterface $transactionDataLoader,
        #[Autowire(service: OrderTransactionRepository::class)]
        private OrderTransactionRepositoryInterface $orderTransactionRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger)
    {
    }

    public function getPaymentByTransactionId(string $transactionId, Context $context): Payment
    {
        $this->logger->info('Loading transaction data', [
            'transactionId' => $transactionId,
        ]);

        $transactionData = $this->transactionDataLoader->findById($transactionId, $context);
        $transaction = $transactionData->getTransaction();
        $transactionOrder = $transactionData->getOrder();

        $orderNumber = (string) $transactionOrder->getOrderNumber();
        $salesChannelId = $transactionOrder->getSalesChannelId();
        $this->logger->info('Loading mollie payment data', [
            'transactionId' => $transactionId,
            'orderNumber' => $orderNumber,
            'salesChannelId' => $salesChannelId,
        ]);
        /** @var ?Payment $mollieTransaction */
        $mollieTransaction = $transaction->getExtension(Mollie::EXTENSION);

        if ($mollieTransaction instanceof Payment) {
            $this->logger->debug('Transaction has mollie payment data, load additional data from mollie', [
                'transactionId' => $transactionId,
                'orderNumber' => $orderNumber,
                'salesChannelId' => $salesChannelId,
                'molliePaymentId' => $mollieTransaction->getId(),
            ]);

            $payment = $this->getPayment($mollieTransaction->getId(), $orderNumber, $salesChannelId);
            $payment->setFinalizeUrl($mollieTransaction->getFinalizeUrl());
        }

        if ($mollieTransaction === null) {
            $this->logger->debug('Transaction is without mollie payment data', [
                'transactionId' => $transactionId,
                'orderNumber' => $orderNumber,
                'salesChannelId' => $salesChannelId,
            ]);
            $payment = $this->repairLegacyTransaction($transaction, $transactionOrder, $context);
            if ($payment === null) {
                throw new TransactionWithoutMollieDataException($transactionId);
            }
        }

        $payment->setShopwareTransaction($transaction);

        $this->logger->info('Payment data were loaded by transaction', [
            'transactionId' => $transactionId,
            'orderNumber' => $orderNumber,
            'salesChannelId' => $salesChannelId,
            'molliePaymentId' => $payment->getId(),
            'paymentStatus' => $payment->getStatus()->value,
        ]);

        return $payment;
    }

    public function createPayment(CreatePayment $molliePayment, string $salesChannelId): Payment
    {
        $shopwareOrderNumber = $molliePayment->getShopwareOrderNumber();
        try {
            $client = $this->clientFactory->create($salesChannelId);
            $formParams = $molliePayment->toArray();

            $response = $client->post('payments', [
                'form_params' => $molliePayment->toArray(),
            ]);
            $body = json_decode($response->getBody()->getContents(), true);

            $this->logger->info('Mollie Payment created', [
                'requestParameter' => $formParams,
                'responseParameter' => $body,
                'orderNumber' => $shopwareOrderNumber,
                'salesChannelId' => $salesChannelId,
            ]);

            return Payment::createFromClientResponse($body);
        } catch (ClientException $exception) {
            throw $this->convertException($exception,$shopwareOrderNumber);
        }
    }

    private function getPayment(string $molliePaymentId, string $orderNumber, string $salesChannelId): Payment
    {
        try {
            $client = $this->clientFactory->create($salesChannelId);
            $response = $client->get('payments/' . $molliePaymentId);
            $body = json_decode($response->getBody()->getContents(), true);
            $this->logger->debug('Additional data from mollie loaded', [
                'molliePaymentId' => $molliePaymentId,
                'orderNumber' => $orderNumber,
                'salesChannelId' => $salesChannelId,
                'body' => $body
            ]);

            return Payment::createFromClientResponse($body);
        } catch (ClientException $exception) {
            throw $this->convertException($exception, $orderNumber);
        }
    }

    private function getPaymentByMollieOrderId(string $mollieOrderId, string $orderNumber, string $salesChannelId): Payment
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

            $this->logger->debug('Additional data from mollie loaded based on mollie order id', [
                'mollieOrderId' => $mollieOrderId,
                'orderNumber' => $orderNumber,
                'salesChannelId' => $salesChannelId,
                'body' => $paymentsBody,
            ]);

            return Payment::createFromClientResponse($paymentsBody);
        } catch (ClientException $exception) {
            throw $this->convertException($exception, $orderNumber);
        }
    }

    private function convertException(ClientException $exception, string $orderNumber): ApiException
    {
        $body = json_decode($exception->getResponse()->getBody()->getContents(), true);

        $this->logger->error('There was an error from Mollies API', [
            'title' => $body['title'] ?? 'no title',
            'error' => $body['detail'] ?? 'no details',
            'field' => $body['field'] ?? 'no field',
            'orderNumber' => $orderNumber,
        ]);

        return new ApiException($exception->getCode(), $body['title'] ?? '', $body['detail'] ?? '', $body['field'] ?? '');
    }

    private function repairLegacyTransaction(OrderTransactionEntity $transaction, OrderEntity $order, Context $context): ?Payment
    {
        $transactionId = $transaction->getId();
        $orderNumber = (string) $order->getOrderNumber();
        $salesChannelId = $order->getSalesChannelId();

        $this->logger->debug('Trying to load data based on order entity', [
            'transactionId' => $transactionId,
            'orderNumber' => $orderNumber,
            'salesChannelId' => $salesChannelId,
        ]);

        $customFields = $order->getCustomFields()[Mollie::EXTENSION] ?? null;
        if ($customFields === null) {
            $this->logger->error('Order does not have mollie custom fields', [
                'transactionId' => $transactionId,
                'orderNumber' => $orderNumber,
                'salesChannelId' => $salesChannelId,
            ]);

            return null;
        }
        $mollieOrderId = $customFields['order_id'] ?? null;
        $returnUrl = $customFields['transactionReturnUrl'] ?? null;
        if ($mollieOrderId === null || $returnUrl === null) {
            $this->logger->error('Order does have mollie custom fields but mollie oder id or return url is not set', [
                'transactionId' => $transactionId,
                'orderNumber' => $orderNumber,
                'salesChannelId' => $salesChannelId,
                'mollieOrderId' => (string) $mollieOrderId,
                'returnUrl' => (string) $returnUrl,
            ]);

            return null;
        }

        $payment = $this->getPaymentByMollieOrderId($mollieOrderId, $orderNumber, $salesChannelId);
        $payment->setFinalizeUrl($returnUrl);

        $this->orderTransactionRepository->savePaymentExtension($transaction, $payment, $context);

        return $payment;
    }
}
