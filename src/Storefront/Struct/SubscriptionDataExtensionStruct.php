<?php

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


    /**
     * @param bool $subscriptionProduct
     * @param string $translatedInterval
     * @param bool $showIndicator
     */
    public function __construct(bool $subscriptionProduct, string $translatedInterval, bool $showIndicator)
    {
        $this->subscriptionProduct = $subscriptionProduct;
        $this->translatedInterval = $translatedInterval;
        $this->showIndicator = $showIndicator;
    }


    /**
     * @return bool
     */
    public function isSubscriptionProduct(): bool
    {
        return $this->subscriptionProduct;
    }

    /**
     * @return string
     */
    public function getTranslatedInterval(): string
    {
        return $this->translatedInterval;
    }

    /**
     * @return bool
     */
    public function isShowIndicator(): bool
    {
        return $this->showIndicator;
    }
}
