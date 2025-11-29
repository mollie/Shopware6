<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Fake;

use GuzzleHttp\Client;
use Mollie\Shopware\Component\Mollie\Gateway\ClientFactoryInterface;

final class FakeClientFactory implements ClientFactoryInterface
{
    public function __construct(private Client $client)
    {
    }

    public function create(string $salesChannelId): Client
    {
        return $this->client;
    }
}
