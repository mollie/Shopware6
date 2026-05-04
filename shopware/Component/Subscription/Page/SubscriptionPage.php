<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Page;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Storefront\Page\Page;

final class SubscriptionPage extends Page
{
    /**
     * @var EntitySearchResult<SubscriptionCollection<SubscriptionEntity>>
     */
    protected EntitySearchResult $subscriptions;

    protected CountryCollection $countries;

    protected SalutationCollection $salutations;

    protected bool $allowAddressEditing = false;

    protected bool $allowPauseResume = false;

    protected bool $allowSkip = false;

    protected bool $allowReorder = true;

    protected bool $allowUpdatePayment = true;

    /**
     * @return EntitySearchResult<SubscriptionCollection<SubscriptionEntity>>
     */
    public function getSubscriptions(): EntitySearchResult
    {
        return $this->subscriptions;
    }

    /**
     * @param EntitySearchResult<SubscriptionCollection<SubscriptionEntity>> $subscriptions
     */
    public function setSubscriptions(EntitySearchResult $subscriptions): void
    {
        $this->subscriptions = $subscriptions;
    }

    public function getCountries(): CountryCollection
    {
        return $this->countries;
    }

    public function setCountries(CountryCollection $countries): void
    {
        $this->countries = $countries;
    }

    public function getSalutations(): SalutationCollection
    {
        return $this->salutations;
    }

    public function setSalutations(SalutationCollection $salutations): void
    {
        $this->salutations = $salutations;
    }

    public function isAllowAddressEditing(): bool
    {
        return $this->allowAddressEditing;
    }

    public function setAllowAddressEditing(bool $allowAddressEditing): void
    {
        $this->allowAddressEditing = $allowAddressEditing;
    }

    public function isAllowPauseResume(): bool
    {
        return $this->allowPauseResume;
    }

    public function setAllowPauseResume(bool $allowPauseResume): void
    {
        $this->allowPauseResume = $allowPauseResume;
    }

    public function isAllowSkip(): bool
    {
        return $this->allowSkip;
    }

    public function setAllowSkip(bool $allowSkip): void
    {
        $this->allowSkip = $allowSkip;
    }

    public function isAllowReorder(): bool
    {
        return $this->allowReorder;
    }

    public function setAllowReorder(bool $allowReorder): void
    {
        $this->allowReorder = $allowReorder;
    }

    public function isAllowUpdatePayment(): bool
    {
        return $this->allowUpdatePayment;
    }

    public function setAllowUpdatePayment(bool $allowUpdatePayment): void
    {
        $this->allowUpdatePayment = $allowUpdatePayment;
    }
}
