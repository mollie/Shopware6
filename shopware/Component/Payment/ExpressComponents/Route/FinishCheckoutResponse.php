<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents\Route;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<\Shopware\Core\Framework\Struct\ArrayStruct<array{sessionId: string, token: string}>>
 */
final class FinishCheckoutResponse extends StoreApiResponse
{
    public function __construct(private string $sessionId, private string $contextToken)
    {
        parent::__construct(new ArrayStruct(
            [
                'sessionId' => $sessionId,
                'token' => $contextToken,
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
}
