<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\RefundManager\DAL\RefundItem;

use Google\Protobuf\Api;
use Kiener\MolliePayments\Components\RefundManager\DAL\Refund\RefundDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

final class RefundItemDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'mollie_refund_item';

    public function getCollectionClass(): string
    {
        return RefundItemCollection::class;
    }

    public function getEntityClass(): string
    {
        return RefundItemEntity::class;
    }

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey(), new ApiAware()),
            (new FkField('refund_id', 'refundId', RefundDefinition::class))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('refund', 'refund_id', RefundDefinition::class))->addFlags(new ApiAware()),
            (new IntField('quantity', 'quantity'))->addFlags(new Required(), new ApiAware()),
            (new StringField('label', 'label'))->addFlags(new ApiAware()),
            (new FloatField('amount', 'amount'))->addFlags(new Required(), new ApiAware()),
            (new StringField('mollie_line_id', 'mollieLineId'))->addFlags(new Required(), new ApiAware()),
            (new FkField('order_line_item_id', 'orderLineItemId', OrderLineItemDefinition::class))->addFlags(new ApiAware()),
            new ReferenceVersionField(OrderLineItemDefinition::class, 'order_line_item_version_id'),
            (new ManyToOneAssociationField('orderLineItem', 'order_line_item_id', OrderLineItemDefinition::class))->addFlags(new ApiAware()),
        ]);
    }
}
