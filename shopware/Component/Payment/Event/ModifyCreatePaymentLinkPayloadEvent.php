<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Event;

use Mollie\Shopware\Component\Mollie\CreatePaymentLink;
use Shopware\Core\Framework\Context;

final class ModifyCreatePaymentLinkPayloadEvent
{
    public function __construct(private CreatePaymentLink $paymentLink, private Context $context)
    {
    }

    public function getPaymentLink(): CreatePaymentLink
    {
        return $this->paymentLink;
    }

    public function setPaymentLink(CreatePaymentLink $paymentLink): void
    {
        $this->paymentLink = $paymentLink;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
