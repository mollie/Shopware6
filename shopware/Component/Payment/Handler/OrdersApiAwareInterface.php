<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Handler;

/**
 * Temporary bridge: handlers implementing this interface still require the
 * Mollie Orders API (/v2/orders). PayPal Express is the only handler that
 * implements it because the authenticationId field is not yet available on
 * the Payments API.
 *
 * Once Mollie ships that field, remove this interface, its implementation on
 * PayPalExpressPayment, PayloadBuilder::buildOrder(), and the branch in Pay.
 *
 * @see docs/refactoring/features/paypal-express-orders-api.md
 */
interface OrdersApiAwareInterface
{
}
