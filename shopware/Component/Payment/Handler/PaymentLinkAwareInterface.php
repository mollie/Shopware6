<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Handler;

/**
 * Mollie accepts this method in a payment link's "allowedMethods". Sending an unsupported one makes
 * Mollie reject the whole request, so only mark methods listed in the Mollie payment link API.
 */
interface PaymentLinkAwareInterface
{
}
