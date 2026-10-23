<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Shopware's context persister is injected by its concrete type. It is not final, so the fake
 * extends it and replaces the constructor - the real one needs a database connection.
 */
final class FakeSalesChannelContextPersister extends SalesChannelContextPersister
{
    /** @var list<array{token: string, parameters: array<string, mixed>, salesChannelId: string, customerId: null|string}> */
    private array $saved = [];

    /** @var list<string> */
    private array $replacedTokens = [];

    public function __construct()
    {
    }

    public function replace(string $oldToken, SalesChannelContext $context): string
    {
        $this->replacedTokens[] = $oldToken;

        return 'replaced-' . $oldToken;
    }

    /**
     * @return list<string>
     */
    public function getReplacedTokens(): array
    {
        return $this->replacedTokens;
    }

    public function save(string $token, array $newParameters, string $salesChannelId, ?string $customerId = null): void
    {
        $this->saved[] = [
            'token' => $token,
            'parameters' => $newParameters,
            'salesChannelId' => $salesChannelId,
            'customerId' => $customerId,
        ];
    }

    /**
     * @return list<array{token: string, parameters: array<string, mixed>, salesChannelId: string, customerId: null|string}>
     */
    public function getSaved(): array
    {
        return $this->saved;
    }
}
