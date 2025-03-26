<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\DAL\Customer;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

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
