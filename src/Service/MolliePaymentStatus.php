<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;


use DateTimeInterface;
use Kiener\MolliePayments\Exception\UnexpectedObjectType;
use Mollie\Api\Resources\Order as MollieOrder;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\PaymentCollection;

/**
 * @copyright 2021 dasistweb GmbH (https://www.dasistweb.de)
 */
class MolliePaymentStatus
{

    /**
     * @param Payment[] $payments
     * @return string|null
     */
    public function getCurrentPaymentStatus(array $payments): ?string
    {
//        $payments = $mollieOrder->payments();
//
//        if (!$payments instanceof PaymentCollection || $payments->count() === 0) {
//            return null;
//        }
//
//        $payments = $payments->getArrayCopy();

        if (count($payments) === 0) {
            return null;
        }

        uasort($payments, function ($paymentItemA, $paymentItemB) {
            if (!$paymentItemA instanceof Payment || !$paymentItemB instanceof Payment) {
                throw new UnexpectedObjectType(Payment::class);
            }
            $timeA = \DateTime::createFromFormat(DateTimeInterface::W3C, $paymentItemA->createdAt);
            $timeB = \DateTime::createFromFormat(DateTimeInterface::W3C, $paymentItemB->createdAt);
            return (int)($timeA < $timeB);
        });

        $payment = array_shift($payments);

        return (string)$payment->status;
    }
}
