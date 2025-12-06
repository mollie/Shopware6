<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<Mandate>
 */
final class MandateCollection extends Collection
{
    public function filterByPaymentMethod(PaymentMethod $paymentMethod): self
    {
        return $this->filter(function (Mandate $mandate) use ($paymentMethod) {
            return $mandate->getMethod() === $paymentMethod;
        });
    }
}
