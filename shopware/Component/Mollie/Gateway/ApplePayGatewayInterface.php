<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

interface ApplePayGatewayInterface
{
    /**
     * @return array<mixed>
     */
    public function requestSession(string $domain, string $validationUrl,string $salesChannelId): array;
}
