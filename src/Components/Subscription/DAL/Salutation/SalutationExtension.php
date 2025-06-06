<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\DAL\Salutation;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Salutation\SalutationDefinition;

class SalutationExtension extends EntityExtension
{
    public function getDefinitionClass(): string
    {
        return SalutationDefinition::class;
    }

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add((new OneToManyAssociationField('subscriptionAddress', SubscriptionAddressDefinition::class, 'salutation_id'))->addFlags(new CascadeDelete()));
    }

    public function getEntityName(): string
    {
        return SalutationDefinition::ENTITY_NAME;
    }
}
