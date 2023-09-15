<?php


namespace Kiener\MolliePayments\Components\Subscription\DAL\Country;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;

class CountryStateExtension extends EntityExtension
{
    /**
     * @return string
     */
    public function getDefinitionClass(): string
    {
        return CountryStateDefinition::class;
    }

    /**
     * @param FieldCollection $collection
     */
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add((new OneToManyAssociationField('subscriptionAddress', SubscriptionAddressDefinition::class, 'country_state_id'))->addFlags(new CascadeDelete()));
    }
}
