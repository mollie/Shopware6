<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\DAL\Order;

use Mollie\Shopware\Component\Refund\DAL\Refund\RefundDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('shopware.entity.extension')]
final class OrderExtension extends EntityExtension
{
    public const REFUND_PROPERTY_NAME = 'mollieRefunds';

    public function getDefinitionClass(): string
    {
        return OrderDefinition::class;
    }

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField(self::REFUND_PROPERTY_NAME, RefundDefinition::class, 'order_id'))
                ->addFlags(new CascadeDelete())
        );
    }

    public function getEntityName(): string
    {
        return OrderDefinition::ENTITY_NAME;
    }
}
