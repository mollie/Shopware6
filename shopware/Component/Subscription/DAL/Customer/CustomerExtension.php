<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\DAL\Customer;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('shopware.entity.extension')]
class CustomerExtension extends EntityExtension
{
    public function getDefinitionClass(): string
    {
        return CustomerDefinition::class;
    }

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add((new OneToManyAssociationField('subscriptions', SubscriptionDefinition::class, 'customer_id'))->addFlags(new CascadeDelete()));
    }

    public function getEntityName(): string
    {
        return CustomerDefinition::ENTITY_NAME;
    }
}
