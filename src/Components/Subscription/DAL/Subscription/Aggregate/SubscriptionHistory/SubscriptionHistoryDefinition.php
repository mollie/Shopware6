<?php

namespace Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionHistory;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class SubscriptionHistoryDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'mollie_subscription_history';

    /**
     * @return string
     */
    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    /**
     * @return string
     */
    public function getEntityClass(): string
    {
        return SubscriptionHistoryEntity::class;
    }

    /**
     * @return string
     */
    public function getCollectionClass(): string
    {
        return SubscriptionHistoryCollection::class;
    }

    /**
     * @return FieldCollection
     */
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([

            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new FkField('subscription_id', 'subscriptionId', SubscriptionDefinition::class))->addFlags(new ApiAware()),

            # --------------------------------------------------------------------------------------------------------------------------

            (new StringField('comment', 'comment')),

            (new StringField('status_from', 'statusFrom')),
            (new StringField('status_to', 'statusTo')),

            (new StringField('mollie_id', 'mollieId')),

            new CreatedAtField(),
            new UpdatedAtField(),

            # --------------------------------------------------------------------------------------------------------------------------

            new ManyToOneAssociationField('subscription', 'subscription_id', SubscriptionDefinition::class, 'id', false),

        ]);
    }
}
