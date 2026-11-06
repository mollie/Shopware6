<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents\DAL;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('shopware.entity.definition', ['entity' => 'mollie_session_payments'])]
final class SessionPaymentDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'mollie_session_payments';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return SessionPaymentEntity::class;
    }

    public function getCollectionClass(): string
    {
        return SessionPaymentCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('session_id', 'sessionId'))->addFlags(new Required()),
            new StringField('payment_id', 'paymentId'),
            // an IdField would get a random id on Shopware 6.5 when no transaction is assigned yet
            new FkField('transaction_id', 'transactionId', OrderTransactionDefinition::class),
        ]);
    }
}
