<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\RefundManager\DAL\OrderLineItem;

use Kiener\MolliePayments\Components\RefundManager\DAL\RefundItem\RefundItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OrderLineItemExtension extends EntityExtension
{
    public const ORDER_LINE_ITEM_PROPERTY_NAME = 'mollieRefundLineItems';

    public function getDefinitionClass(): string
    {
        return OrderLineItemDefinition::class;
    }

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(new OneToManyAssociationField(self::ORDER_LINE_ITEM_PROPERTY_NAME, RefundItemDefinition::class, 'order_line_item_id'));
    }
}
