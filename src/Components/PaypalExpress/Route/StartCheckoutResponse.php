<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\PaypalExpress\Route;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @final
 */
class StartCheckoutResponse extends StoreApiResponse
{
    private string $sessionId;
    private ?string $redirectUrl;

    public function __construct(string $sessionId, ?string $redirectUrl)
    {
        $this->sessionId = $sessionId;
        $this->redirectUrl = $redirectUrl;
        parent::__construct(new ArrayStruct(
            [
                'sessionId' => $sessionId,
                'redirectUrl' => $redirectUrl,
            ],
            'paypal_express_start_checkout_response'
        ));
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getRedirectUrl(): ?string
    {
        return $this->redirectUrl;
    }
}
