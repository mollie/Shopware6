<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<Payment>
 */
final class PaymentCollection extends Collection
{
    public function filterCancelable(): self
    {
        return $this->filter(function (Payment $payment) {
            return $payment->isCancelable();
        });
    }
}
