<?php

namespace Kiener\MolliePayments\Components\Subscription\Page\Account;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Shopware\Storefront\Page\Page;


class SubscriptionPage extends Page
{
    /**
     * @var StorefrontSearchResult<SubscriptionEntity>
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
     * @var CountryCollection
     */
    protected $countries;

    /**
     * @var SalutationCollection
     */
    protected $salutations;


    /**
     * @return StorefrontSearchResult<SubscriptionEntity>
     */
    public function getSubscriptions(): StorefrontSearchResult
    {
        return $this->subscriptions;
    }

    /**
     * @param StorefrontSearchResult<SubscriptionEntity> $subscriptions
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

    /**
     * @param CountryCollection $countries
     */
    public function setCountries(CountryCollection $countries): void
    {
        $this->countries = $countries;
    }

    /**
     * @return CountryCollection
     */
    public function getCountries(): CountryCollection
    {
        return $this->countries;
    }

    /**
     * @return SalutationCollection
     */
    public function getSalutations(): SalutationCollection
    {
        return $this->salutations;
    }

    /**
     * @param SalutationCollection $salutations
     */
    public function setSalutations(SalutationCollection $salutations): void
    {
        $this->salutations = $salutations;
    }

}
