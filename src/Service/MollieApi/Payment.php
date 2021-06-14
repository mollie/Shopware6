<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Exception\PaymentCouldNotBeCancelled;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\PaymentCollection;

class Payment
{
    /**
     * @var MollieApiFactory
     */
    private $clientFactory;

    public function __construct(MollieApiFactory $clientFactory)
    {
        $this->clientFactory = $clientFactory;
    }

    public function delete(string $molliePaymentId, string $orderSalesChannelContextId): void
    {
        $apiClient = $this->clientFactory->getClient($orderSalesChannelContextId);

        try {
            $apiClient->payments->delete($molliePaymentId);
        } catch (ApiException $e) {
            throw new PaymentCouldNotBeCancelled($molliePaymentId, [], $e);
        }
    }

    public function cancelOpenPayments(?PaymentCollection $payments, string $salesChannelContextId): void
    {
        if (!$payments instanceof PaymentCollection) {
            return;
        }

        /** @var \Mollie\Api\Resources\Payment $payment */
        foreach ($payments as $payment) {
            if ($payment->isOpen() && $payment->isCancelable) {
                $this->delete($payment->id, $salesChannelContextId);
            }
        }
    }
}
