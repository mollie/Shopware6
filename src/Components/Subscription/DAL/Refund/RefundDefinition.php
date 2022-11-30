<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\DAL\Refund;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class RefundDefinition extends EntityDefinition
{

    public const ENTITY_NAME = 'mollie_refund';

    /**
     * @return string
     */
    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return RefundEntity::class;
    }

    public function getCollectionClass(): string
    {
        return RefundCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),

            # --------------------------------------------------------------------------------------------------------------------------

            (new FkField('order_id', 'orderId', OrderDefinition::class)),

            (new StringField('mollie_refund_id', 'mollieRefundId')),

            new LongTextField('public_description', 'publicDescription'),
            new LongTextField('internal_description', 'internalDescription'),
        ]);
    }
}
