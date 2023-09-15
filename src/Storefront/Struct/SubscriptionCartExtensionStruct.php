<?php

namespace Kiener\MolliePayments\Storefront\Struct;

use Shopware\Core\Framework\Struct\Struct;

class SubscriptionCartExtensionStruct extends Struct
{
    /**
     * @var bool
     */
    protected $hasSubscription = false;

    /**
     * @param bool $hasSubscription
     */
    public function __construct(bool $hasSubscription)
    {
        $this->hasSubscription = $hasSubscription;
    }

    /**
     * @return bool
     */
    public function isHasSubscription(): bool
    {
        return $this->hasSubscription;
    }

    /**
     * @param bool $hasSubscription
     */
    public function setHasSubscription(bool $hasSubscription): void
    {
        $this->hasSubscription = $hasSubscription;
    }
}
