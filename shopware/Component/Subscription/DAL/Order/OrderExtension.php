<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\DAL\Order;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('shopware.entity.extension')]
final class OrderExtension extends EntityExtension
{
    public function getDefinitionClass(): string
    {
        return OrderDefinition::class;
    }

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(new OneToManyAssociationField('mollieSubscriptions', SubscriptionDefinition::class, 'order_id'));
    }

    public function getEntityName(): string
    {
        return OrderDefinition::ENTITY_NAME;
    }
}
