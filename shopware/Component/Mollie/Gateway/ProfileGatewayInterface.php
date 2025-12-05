<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use Mollie\Shopware\Component\Mollie\Profile;

interface ProfileGatewayInterface
{
    public function getCurrentProfile(string $salesChannelId): Profile;
}
