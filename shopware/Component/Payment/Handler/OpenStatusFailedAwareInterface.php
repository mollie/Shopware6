<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Handler;

/**
 * Marks payment methods that never keep an "open" payment alive after the customer returns from
 * the Mollie page: the attempt is over, so "open" means the payment will never be paid. Methods
 * without this interface may legitimately stay "open" for a while (e.g. bank transfer) and are
 * finalized as success.
 */
interface OpenStatusFailedAwareInterface
{
}
