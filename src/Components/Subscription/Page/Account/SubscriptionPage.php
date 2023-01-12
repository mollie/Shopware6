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
     * @var null|string
     */
    protected $deepLinkCode;

    /**
     * @var null|int
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
     * @var bool
     */
    protected $allowAddressEditing;

    /**
     * @var bool
     */
    protected $allowPauseResume;

    /**
     * @var bool
     */
    protected $allowSkip;


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
     * @return null|string
     */
    public function getDeepLinkCode(): ?string
    {
        return $this->deepLinkCode;
    }

    /**
     * @param null|string $deepLinkCode
     */
    public function setDeepLinkCode(?string $deepLinkCode): void
    {
        $this->deepLinkCode = $deepLinkCode;
    }

    /**
     * @return null|int
     */
    public function getTotal(): ?int
    {
        return $this->total;
    }

    /**
     * @param null|int $total
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

    /**
     * ATTENTION: DON'T DELETE.
     * The IDE says it's not used, but it's
     * indeed used in the TWIG storefront :)
     *
     * @return bool
     */
    public function isAllowAddressEditing(): bool
    {
        return $this->allowAddressEditing;
    }

    /**
     * @param bool $allowAddressEditing
     */
    public function setAllowAddressEditing(bool $allowAddressEditing): void
    {
        $this->allowAddressEditing = $allowAddressEditing;
    }

    /**
     * @return bool
     */
    public function isAllowPauseResume(): bool
    {
        return $this->allowPauseResume;
    }

    /**
     * @param bool $allowPauseResume
     */
    public function setAllowPauseResume(bool $allowPauseResume): void
    {
        $this->allowPauseResume = $allowPauseResume;
    }

    /**
     * @return bool
     */
    public function isAllowSkip(): bool
    {
        return $this->allowSkip;
    }

    /**
     * @param bool $allowSkip
     */
    public function setAllowSkip(bool $allowSkip): void
    {
        $this->allowSkip = $allowSkip;
    }
}
