<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Storefront\Struct;

use Shopware\Core\Framework\Struct\Struct;

class SubscriptionCartExtensionStruct extends Struct
{
    /**
     * @var bool
     */
    protected $hasSubscription = false;

    public function __construct(bool $hasSubscription)
    {
        $this->hasSubscription = $hasSubscription;
    }

    public function isHasSubscription(): bool
    {
        return $this->hasSubscription;
    }

    public function setHasSubscription(bool $hasSubscription): void
    {
        $this->hasSubscription = $hasSubscription;
    }
}
