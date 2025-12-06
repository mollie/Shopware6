<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<Mandate>
 */
final class MandateCollection extends Collection
{
    /**
     * @return Collection<Mandate>
     */
    public function filterByPaymentMethod(PaymentMethod $paymentMethod): Collection
    {
        return $this->filter(function (Mandate $mandate) use ($paymentMethod) {
            return $mandate->getMethod() === $paymentMethod;
        });
    }
}
