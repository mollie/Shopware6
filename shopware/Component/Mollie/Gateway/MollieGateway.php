<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use GuzzleHttp\Exception\ClientException;
use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\Customer;
use Mollie\Shopware\Component\Mollie\Exception\ApiException;
use Mollie\Shopware\Component\Mollie\Exception\TransactionWithoutMollieDataException;
use Mollie\Shopware\Component\Mollie\Locale;
use Mollie\Shopware\Component\Mollie\Mandate;
use Mollie\Shopware\Component\Mollie\MandateCollection;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\Profile;
use Mollie\Shopware\Component\Transaction\TransactionService;
use Mollie\Shopware\Component\Transaction\TransactionServiceInterface;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Language\LanguageEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class MollieGateway implements MollieGatewayInterface
{
    public function __construct(
        #[Autowire(service: ClientFactory::class)]
        private ClientFactoryInterface $clientFactory,
        #[Autowire(service: TransactionService::class)]
        private TransactionServiceInterface $transactionService,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public function getPaymentByTransactionId(string $transactionId, Context $context): Payment
    {
        $logData = [
            'transactionId' => $transactionId,
        ];
        $this->logger->info('Loading transaction data', $logData);

        $transactionData = $this->transactionService->findById($transactionId, $context);

        $transaction = $transactionData->getTransaction();
        $transactionOrder = $transactionData->getOrder();

        $orderNumber = (string) $transactionOrder->getOrderNumber();
        $salesChannelId = $transactionOrder->getSalesChannelId();
        $logData['orderNumber'] = $orderNumber;
        $logData['salesChannelId'] = $salesChannelId;
        $this->logger->info('Loading mollie payment data', $logData);

        /** @var ?Payment $mollieTransaction */
        $mollieTransaction = $transaction->getExtension(Mollie::EXTENSION);

        if ($mollieTransaction instanceof Payment) {
            $logData['molliePaymentId'] = $mollieTransaction->getId();
            $this->logger->debug('Transaction has mollie payment data, load additional data from mollie', $logData);

            $payment = $this->getPayment($mollieTransaction->getId(), $orderNumber, $salesChannelId);
            $payment->setFinalizeUrl($mollieTransaction->getFinalizeUrl());
        }

        if ($mollieTransaction === null) {
            $this->logger->debug('Transaction is without mollie payment data', $logData);
            $payment = $this->repairLegacyTransaction($transaction, $transactionOrder, $context);
            if ($payment === null) {
                throw new TransactionWithoutMollieDataException($transactionId);
            }
        }

        $payment->setShopwareTransaction($transaction);
        $logData['molliePaymentId'] = $payment->getId();
        $logData['paymentStatus'] = $payment->getStatus()->value;
        $this->logger->info('Payment data were loaded by transaction', $logData);

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
            throw $this->convertException($exception, $shopwareOrderNumber);
        }
    }

    public function getCurrentProfile(?string $salesChannelId = null): Profile
    {
        try {
            $client = $this->clientFactory->create($salesChannelId);
            $response = $client->get('profiles/me');
            $body = json_decode($response->getBody()->getContents(), true);
            $this->logger->debug('Fetched profile Id from Mollie');

            return Profile::fromClientResponse($body);
        } catch (ClientException $exception) {
            throw $this->convertException($exception);
        }
    }

    public function createCustomer(CustomerEntity $customer, string $salesChannelId): Customer
    {
        try {
            $client = $this->clientFactory->create($salesChannelId);
            $formParams = [
                'name' => sprintf('%s %s', $customer->getFirstName(), $customer->getLastName()),
                'email' => $customer->getEmail(),
                'metadata' => [
                    'shopwareCustomerNumber' => $customer->getCustomerNumber(),
                ]
            ];
            $customerLanguage = $customer->getLanguage();
            if ($customerLanguage instanceof LanguageEntity) {
                $formParams['locale'] = Locale::fromLanguage($customerLanguage);
            }
            $response = $client->post('customers', [
                'form_params' => $formParams
            ]);
            $body = json_decode($response->getBody()->getContents(), true);

            return Customer::fromClientResponse($body);
        } catch (ClientException $exception) {
            throw $this->convertException($exception);
        }
    }

    public function listMandates(string $mollieCustomerId, string $salesChannelId): MandateCollection
    {
        try {
            $client = $this->clientFactory->create($salesChannelId);
            $response = $client->get(sprintf('customers/%s/mandates', $mollieCustomerId));
            $body = json_decode($response->getBody()->getContents(), true);
            $collection = new MandateCollection();
            foreach ($body['_embedded']['mandates'] as $mandateData) {
                $mandate = Mandate::fromClientResponse($mandateData);
                $collection->set($mandate->getId(), $mandate);
            }

            return $collection;
        } catch (ClientException $exception) {
            throw $this->convertException($exception);
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

    private function convertException(ClientException $exception, ?string $orderNumber = null): ApiException
    {
        $body = json_decode($exception->getResponse()->getBody()->getContents(), true);
        $logData = [
            'title' => $body['title'] ?? 'no title',
            'error' => $body['detail'] ?? 'no details',
            'field' => $body['field'] ?? 'no field',
        ];
        if ($orderNumber !== null) {
            $logData['orderNumber'] = $orderNumber;
        }
        $this->logger->error('There was an error from Mollies API', $logData);

        return new ApiException($exception->getCode(), $body['title'] ?? '', $body['detail'] ?? '', $body['field'] ?? '');
    }

    private function repairLegacyTransaction(OrderTransactionEntity $transaction, OrderEntity $order, Context $context): ?Payment
    {
        $transactionId = $transaction->getId();
        $orderNumber = (string) $order->getOrderNumber();
        $salesChannelId = $order->getSalesChannelId();
        $logData = [
            'transactionId' => $transactionId,
            'orderNumber' => $orderNumber,
            'salesChannelId' => $salesChannelId,
        ];
        $this->logger->debug('Trying to load data based on order entity', $logData);

        $customFields = $order->getCustomFields()[Mollie::EXTENSION] ?? null;
        if ($customFields === null) {
            $this->logger->error('Order does not have mollie custom fields', $logData);

            return null;
        }
        $mollieOrderId = $customFields['order_id'] ?? null;
        $returnUrl = $customFields['transactionReturnUrl'] ?? null;
        if ($mollieOrderId === null || $returnUrl === null) {
            $logData['mollieOrderId'] = $mollieOrderId;
            $logData['returnUrl'] = $returnUrl;

            $this->logger->error('Order does have mollie custom fields but mollie oder id or return url is not set', $logData);

            return null;
        }

        $payment = $this->getPaymentByMollieOrderId($mollieOrderId, $orderNumber, $salesChannelId);
        $payment->setFinalizeUrl($returnUrl);

        $this->transactionService->savePaymentExtension($transactionId, $order, $payment, $context);

        return $payment;
    }
}
