<?php
declare(strict_types=1);

namespace Mollie\Shopware\Entity\PaymentMethod;

use Mollie\Shopware\Component\Mollie\PaymentMethod as MolliePaymentMethod;
use Shopware\Core\Framework\Struct\Struct;

final class PaymentMethod extends Struct
{
    public function __construct(private string $id,private MolliePaymentMethod $paymentMethod)
    {
    }

    public function getPaymentMethod(): MolliePaymentMethod
    {
        return $this->paymentMethod;
    }

    public function getId(): string
    {
        return $this->id;
    }
}
