<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Event;

use Mollie\Shopware\Component\Mollie\CreateSubscription;
use Shopware\Core\Framework\Context;

final class ModifyCreateSubscriptionPayloadEvent
{
    public function __construct(
        private readonly CreateSubscription $createSubscription,
        private readonly Context $context)
    {

    }

    public function getCreateSubscription(): CreateSubscription
    {
        return $this->createSubscription;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

}