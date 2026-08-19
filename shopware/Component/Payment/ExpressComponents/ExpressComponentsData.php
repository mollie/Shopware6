<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Shopware\Core\Framework\Struct\Struct;

/**
 * Page extension carrying everything the storefront needs to mount the express component.
 */
final class ExpressComponentsData extends Struct
{
    public const EXTENSION = 'mollieExpressComponents';

    /**
     * @param string[] $restrictions
     */
    public function __construct(
        private bool $enabled,
        private array $restrictions = [],
        private string $sessionId = '',
        private string $clientAccessToken = ''
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return string[]
     */
    public function getRestrictions(): array
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
