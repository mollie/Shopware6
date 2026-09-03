<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Handler;

/**
 * Marks a method Mollie can capture automatically as well as manually, so the merchant chooses per
 * method whether the money is collected right at the checkout or only held until the shipment.
 *
 * Riverty is missing because Mollie holds its money in any case, and PayPal because its manual
 * capture is still in beta and not generally available.
 *
 * @see https://docs.mollie.com/docs/place-a-hold-for-a-payment#payment-method-support
 */
interface AutomaticCaptureAwareInterface
{
}
