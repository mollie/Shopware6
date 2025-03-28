<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\DAL\Country;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;

class CountryStateExtension extends EntityExtension
{
    public function getDefinitionClass(): string
    {
        return CountryStateDefinition::class;
    }

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add((new OneToManyAssociationField('subscriptionAddress', SubscriptionAddressDefinition::class, 'country_state_id'))->addFlags(new CascadeDelete()));
    }

    public function getEntityName(): string
    {
        return CountryStateDefinition::ENTITY_NAME;
    }
}
