<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\PaypalExpress\Route;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @final
 */
class CancelCheckoutResponse extends StoreApiResponse
{
    private string $sessionId;


    public function __construct(string $sessionId)
    {
        $this->sessionId = $sessionId;

        parent::__construct(new ArrayStruct(
            [
                'sessionId' => $sessionId,
            ],
            'paypal_express_cancel_checkout_response'
        ));
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }
}
