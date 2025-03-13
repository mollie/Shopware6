<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Storefront\Struct;

use Shopware\Core\Framework\Struct\Struct;

class SubscriptionDataExtensionStruct extends Struct
{
    /**
     * @var bool
     */
    protected $subscriptionProduct = false;

    /**
     * @var string
     */
    protected $translatedInterval = '';

    /**
     * @var bool
     */
    protected $showIndicator = false;

    public function __construct(bool $subscriptionProduct, string $translatedInterval, bool $showIndicator)
    {
        $this->subscriptionProduct = $subscriptionProduct;
        $this->translatedInterval = $translatedInterval;
        $this->showIndicator = $showIndicator;
    }

    public function isSubscriptionProduct(): bool
    {
        return $this->subscriptionProduct;
    }

    public function getTranslatedInterval(): string
    {
        return $this->translatedInterval;
    }

    public function isShowIndicator(): bool
    {
        return $this->showIndicator;
    }
}
