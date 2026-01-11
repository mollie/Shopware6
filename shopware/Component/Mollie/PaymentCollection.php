<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<Payment>
 */
final class PaymentCollection extends Collection
{

    public function findById(string $molliePaymentId):?Payment
    {
        /** @var Payment $payment */
        foreach($this->getElements() as $payment) {
            if($payment->getId() === $molliePaymentId) {
                return $payment;
            }
        }
        return null;
    }
}