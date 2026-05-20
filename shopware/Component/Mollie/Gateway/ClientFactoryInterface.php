<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use GuzzleHttp\Client;

interface ClientFactoryInterface
{
    public function create(?string $salesChannelId = null,bool $forceLive = false): Client;
}
