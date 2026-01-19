<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use Mollie\Shopware\Component\Mollie\Capture;
use Mollie\Shopware\Component\Mollie\CreateCapture;

interface CaptureGatewayInterface
{
    public function createCapture(CreateCapture $createCapture, string $paymentId, string $salesChannelId): Capture;
}
