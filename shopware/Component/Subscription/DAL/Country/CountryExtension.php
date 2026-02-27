<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\DAL\Country;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Country\CountryDefinition;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('shopware.entity.extension')]
class CountryExtension extends EntityExtension
{
    public function getDefinitionClass(): string
    {
        return CountryDefinition::class;
    }

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add((new OneToManyAssociationField('subscriptionAddress', SubscriptionAddressDefinition::class, 'country_id'))->addFlags(new CascadeDelete()));
    }

    public function getEntityName(): string
    {
        return CountryDefinition::ENTITY_NAME;
    }
}
