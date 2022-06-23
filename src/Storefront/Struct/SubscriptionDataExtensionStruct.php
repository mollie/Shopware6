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
     * @var string string
     */
    protected $translatedInterval = '';

    /**
     * @param bool $subscriptionProduct
     * @param string $translatedInterval
     */
    public function __construct(bool $subscriptionProduct, string $translatedInterval)
    {
        $this->subscriptionProduct = $subscriptionProduct;
        $this->translatedInterval = $translatedInterval;
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

}
