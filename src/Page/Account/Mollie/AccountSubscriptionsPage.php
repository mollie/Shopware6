<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Page\Account\Mollie;

use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Shopware\Storefront\Page\Page;

class AccountSubscriptionsPage extends Page
{
    /**
     * @var StorefrontSearchResult
     */
    protected StorefrontSearchResult $subscriptions;

    /**
     * @var string|null
     */
    protected ?string $deepLinkCode;

    protected ?int $total;

    /**
     * @return StorefrontSearchResult
     */
    public function getSubscriptions(): StorefrontSearchResult
    {
        return $this->subscriptions;
    }

    /**
     * @param StorefrontSearchResult $subscriptions
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
