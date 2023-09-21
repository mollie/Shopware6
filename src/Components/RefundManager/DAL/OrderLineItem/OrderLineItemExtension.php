<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\RefundManager\DAL\OrderLineItem;

use Kiener\MolliePayments\Components\RefundManager\DAL\Refund\RefundDefinition;
use Kiener\MolliePayments\Components\RefundManager\DAL\RefundItem\RefundItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

final class OrderLineItemExtension extends EntityExtension
{
    const ORDER_LINE_ITEM_PROPERTY_NAME = 'refundLineItem';

    /**
     * @return string
     */
    public function getDefinitionClass(): string
    {
        return OrderLineItemDefinition::class;
    }

    /**
     * @param FieldCollection $collection
     */
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(new OneToOneAssociationField(self::ORDER_LINE_ITEM_PROPERTY_NAME, 'id', 'order_line_item_id', RefundItemDefinition::class));
    }
}
