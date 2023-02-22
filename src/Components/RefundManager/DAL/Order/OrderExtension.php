<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\RefundManager\DAL\Order;

use Kiener\MolliePayments\Components\RefundManager\DAL\Refund\RefundDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OrderExtension extends EntityExtension
{
    const REFUND_PROPERTY_NAME = 'mollie_refunds';

    /**
     * @return string
     */
    public function getDefinitionClass(): string
    {
        return OrderDefinition::class;
    }

    /**
     * @param FieldCollection $collection
     */
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add((new OneToManyAssociationField(self::REFUND_PROPERTY_NAME, RefundDefinition::class, 'order_id'))->addFlags(new CascadeDelete()));
    }
}
