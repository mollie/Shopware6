<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\RefundManager\DAL\RefundItem;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
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
            (new StringField('type', 'type'))->addFlags(new Required(), new ApiAware()),
            (new StringField('refund_id', 'refundId'))->addFlags(new Required(), new ApiAware()),
            (new IntField('quantity', 'quantity'))->addFlags(new Required(), new ApiAware()),
            (new FloatField('amount', 'amount'))->addFlags(new Required(), new ApiAware()),
            (new StringField('mollie_line_id', 'mollieLineId'))->addFlags(new Required(), new ApiAware()),
            (new FkField('line_item_id', 'lineItemId', OrderLineItemDefinition::class))->addFlags(new ApiAware()),
        ]);
    }
}
