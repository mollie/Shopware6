<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Exception\MollieOrderCouldNotBeFetched;
use Kiener\MolliePayments\Exception\MollieOrderPaymentCouldNotBeCreated;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\MollieApi\Payment as PaymentApiService;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Order as MollieOrder;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\PaymentCollection;
use Monolog\Logger;
use RuntimeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class Order
{
    /**
     * @var MollieApiFactory
     */
    private $clientFactory;

    /**
     * @var PaymentApiService
     */
    private $paymentApiService;

    /**
     * @var LoggerService
     */
    private $logger;


    public function __construct(MollieApiFactory $clientFactory, PaymentApiService $paymentApiService, LoggerService $logger)
    {
        $this->clientFactory = $clientFactory;
        $this->logger = $logger;
        $this->paymentApiService = $paymentApiService;
    }

    public function getOrder(string $mollieOrderId, SalesChannelContext $salesChannelContext, array $parameters = []): ?MollieOrder
    {
        $apiClient = $this->clientFactory->getClient($salesChannelContext->getSalesChannelId());

        try {
            $mollieOrder = $apiClient->orders->get($mollieOrderId, $parameters);
        } catch (ApiException $e) {
            $this->logger->error(
                sprintf(
                    'API error occured when fetching mollie order %s with message %s',
                    $mollieOrderId,
                    $e->getMessage()
                ),
                $e->getTrace()
            );

            return null;
        }

        return $mollieOrder;
    }

    public function createOrder(array $orderData, string $orderSalesChannelContextId, SalesChannelContext $salesChannelContext): MollieOrder
    {
        $apiClient = $this->clientFactory->getClient($orderSalesChannelContextId);

        /**
         * Create an order at Mollie based on the prepared array of order data.
         */
        try {
            return $apiClient->orders->create($orderData);
        } catch (ApiException $e) {
            $this->logger->addEntry(
                $e->getMessage(),
                $salesChannelContext->getContext(),
                $e,
                [
                    'function' => 'finalize-payment',
                ],
                Logger::CRITICAL
            );

            throw new RuntimeException(sprintf('Could not create Mollie order, error: %s', $e->getMessage()));
        }
    }

    /**
     * function creates a new payment at mollie
     *
     * if an open payment in mollie order exists, this payment is used but is updated by correct payment method
     *
     * @param string $mollieOrderId
     * @param string $paymentMethod
     * @param SalesChannelContext $salesChannelContext
     * @return Payment
     */
    public function createOrReusePayment(string $mollieOrderId, string $paymentMethod, SalesChannelContext $salesChannelContext): Payment
    {
        $mollieOrder = $this->getOrder($mollieOrderId, $salesChannelContext, ['embed' => 'payments']);

        if (!$mollieOrder instanceof MollieOrder) {

            throw new MollieOrderCouldNotBeFetched($mollieOrderId);
        }

        $payment = $this->getOpenPayment($mollieOrder);

        if (!$payment instanceof Payment) {

            return $mollieOrder->createPayment(['method' => $paymentMethod]);
        }

        if ($payment->method === $paymentMethod) {

            return $payment;
        }

        if (!$payment->isCancelable) {

            return $payment;
        }

        try {
            $this->paymentApiService->delete($payment->id, $salesChannelContext->getSalesChannelId());

            /** @var Payment $payment */
            $payment = $mollieOrder->createPayment(['method' => $paymentMethod]);

            return $payment;
        } catch (ApiException $e) {

            throw new MollieOrderPaymentCouldNotBeCreated($mollieOrderId, [], $e);
        }
    }

    private function getOpenPayment(MollieOrder $mollieOrder): ?Payment
    {
        $payments = $mollieOrder->payments();

        if (!$payments instanceof PaymentCollection) {
            return null;
        }

        /** @var Payment $payment */
        foreach ($payments as $payment) {
            if ($payment->isOpen()) {

                return $payment;
            }
        }

        return null;
    }

    public function setShipment(string $mollieOrderId, string $salesChannelId, Context $context): bool
    {
        $apiClient = $this->clientFactory->getClient($salesChannelId);

        try {
            $mollieOrder = $apiClient->orders->get($mollieOrderId);
        } catch (ApiException $e) {
            $this->logger->addEntry(
                sprintf(
                    'API error occured when fetching mollie order %s with message %s',
                    $mollieOrderId,
                    $e->getMessage()
                ),
                $context,
                $e,
                null,
                Logger::ERROR
            );

            throw $e;
        }

        /** @var OrderLine $orderLine */
        foreach ($mollieOrder->lines() as $orderLine) {
            if ($orderLine->shippableQuantity > 0) {
                $mollieOrder->shipAll();

                return true;
            }
        }

        return false;
    }
}
