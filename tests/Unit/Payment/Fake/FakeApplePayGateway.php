<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Mollie\Gateway\ApplePayGatewayInterface;

final class FakeApplePayGateway implements ApplePayGatewayInterface
{
    private bool $shouldThrow = false;

    public function setShouldThrow(bool $shouldThrow): void
    {
        $this->shouldThrow = $shouldThrow;
    }

    public function requestSession(string $domain, string $validationUrl, string $salesChannelId): array
    {
        if ($this->shouldThrow) {
            throw new \RuntimeException('Fake apple pay gateway error');
        }

        return ['id' => 'fake-session-id', 'domain' => $domain];
    }
}
