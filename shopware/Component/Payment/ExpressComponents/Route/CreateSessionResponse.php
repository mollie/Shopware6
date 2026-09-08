<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents\Route;

use Mollie\Shopware\Component\Payment\ExpressMethod\VisibilityRestrictionCollection;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<\Shopware\Core\Framework\Struct\ArrayStruct<array{enabled: bool, restrictions: string[], sessionId: string, clientAccessToken: string}>>
 */
final class CreateSessionResponse extends StoreApiResponse
{
    public function __construct(
        private bool $enabled,
        private VisibilityRestrictionCollection $restrictions,
        private string $sessionId = '',
        private string $clientAccessToken = ''
    ) {
        parent::__construct(new ArrayStruct(
            [
                'enabled' => $enabled,
                'restrictions' => $restrictions->toArray(),
                'sessionId' => $sessionId,
                'clientAccessToken' => $clientAccessToken,
            ],
            'express_components_create_session_response',
        ));
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getRestrictions(): VisibilityRestrictionCollection
    {
        return $this->restrictions;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getClientAccessToken(): string
    {
        return $this->clientAccessToken;
    }
}
