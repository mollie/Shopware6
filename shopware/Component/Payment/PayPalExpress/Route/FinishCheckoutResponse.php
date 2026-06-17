<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\PayPalExpress\Route;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<\Shopware\Core\Framework\Struct\ArrayStruct<array{sessionId: string, authenticateId: string, token: string}>>
 */
final class FinishCheckoutResponse extends StoreApiResponse
{
    private string $sessionId;
    private string $authenticateId;
    private string $contextToken;

    public function __construct(string $sessionId, string $authenticateId, string $contextToken)
    {
        $this->sessionId = $sessionId;
        $this->authenticateId = $authenticateId;
        $this->contextToken = $contextToken;
        parent::__construct(new ArrayStruct(
            [
                'sessionId' => $sessionId,
                'authenticateId' => $authenticateId,
                'token' => $contextToken,
            ],
            'paypal_express_finish_checkout_response',
        ));
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getAuthenticateId(): string
    {
        return $this->authenticateId;
    }

    public function getContextToken(): string
    {
        return $this->contextToken;
    }
}
