<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\DAL\Currency;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Currency\CurrencyDefinition;

class CurrencyExtension extends EntityExtension
{
    public function getDefinitionClass(): string
    {
        return CurrencyDefinition::class;
    }

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add((new OneToManyAssociationField('subscriptions', SubscriptionDefinition::class, 'currency_id'))->addFlags(new CascadeDelete()));
    }
}
