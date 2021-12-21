<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Page\Account\Mollie;

use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Shopware\Storefront\Page\Page;
use Kiener\MolliePayments\Core\Content\SubscriptionToProduct\SubscriptionToProductEntity;

class AccountSubscriptionsPage extends Page
{
    /**
     * @var StorefrontSearchResult<SubscriptionToProductEntity>
     */
    protected $subscriptions;

    /**
     * @var string|null
     */
    protected $deepLinkCode;

    /**
     * @var int|null
     */
    protected $total;

    /**
     * @return StorefrontSearchResult<SubscriptionToProductEntity>
     */
    public function getSubscriptions(): StorefrontSearchResult
    {
        return $this->subscriptions;
    }

    /**
     * @param StorefrontSearchResult<SubscriptionToProductEntity> $subscriptions
     */
    public function setSubscriptions(StorefrontSearchResult $subscriptions): void
    {
        $this->subscriptions = $subscriptions;
    }

    /**
     * @return string|null
     */
    public function getDeepLinkCode(): ?string
    {
        return $this->deepLinkCode;
    }

    /**
     * @param string|null $deepLinkCode
     */
    public function setDeepLinkCode(?string $deepLinkCode): void
    {
        $this->deepLinkCode = $deepLinkCode;
    }

    /**
     * @return int|null
     */
    public function getTotal(): ?int
    {
        return $this->total;
    }

    /**
     * @param int|null $total
     */
    public function setTotal(?int $total): void
    {
        $this->total = $total;
    }
}
