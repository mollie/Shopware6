<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents\Route;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<\Shopware\Core\Framework\Struct\ArrayStruct<array{sessionId: string, token: string, orderId: string, orderNumber: string, redirectUrl: string}>>
 */
final class FinishCheckoutResponse extends StoreApiResponse
{
    public function __construct(
        private string $sessionId,
        private string $contextToken,
        private string $orderId,
        private string $orderNumber,
        private string $redirectUrl
    ) {
        parent::__construct(new ArrayStruct(
            [
                'sessionId' => $sessionId,
                'token' => $contextToken,
                'orderId' => $orderId,
                'orderNumber' => $orderNumber,
                'redirectUrl' => $redirectUrl,
            ],
            'express_components_finish_checkout_response',
        ));
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getContextToken(): string
    {
        return $this->contextToken;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    /**
     * Where Mollie expects the shopper to continue after the order was created.
     */
    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }
}
