<?php


namespace Kiener\MolliePayments\Components\Subscription\DAL\Country;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressDefinition;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Country\CountryDefinition;

class CountryExtension extends EntityExtension
{
    /**
     * @return string
     */
    public function getDefinitionClass(): string
    {
        return CountryDefinition::class;
    }

    /**
     * @param FieldCollection $collection
     */
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add((new OneToManyAssociationField('subscriptionAddress', SubscriptionAddressDefinition::class, 'country_id'))->addFlags(new CascadeDelete()));
    }
}
