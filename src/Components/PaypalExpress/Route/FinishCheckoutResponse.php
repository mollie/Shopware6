<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\PaypalExpress\Route;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @final
 */
class FinishCheckoutResponse extends StoreApiResponse
{
    private string $sessionId;
    private string $authenticateId;

    public function __construct(string $sessionId, string $authenticateId)
    {
        $this->sessionId = $sessionId;
        $this->authenticateId = $authenticateId;
        parent::__construct(new ArrayStruct(
            [
                'sessionId' => $sessionId,
                'authenticateId' => $authenticateId,
            ],
            'paypal_express_finish_checkout_response'
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
}
