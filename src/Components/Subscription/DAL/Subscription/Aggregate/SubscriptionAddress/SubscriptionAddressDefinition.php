<?php

namespace Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Salutation\SalutationDefinition;

class SubscriptionAddressDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'mollie_subscription_address';

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
        return SubscriptionAddressEntity::class;
    }

    /**
     * @return string
     */
    public function getCollectionClass(): string
    {
        return SubscriptionAddressCollection::class;
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

            (new FkField('salutation_id', 'salutationId', SalutationDefinition::class))->addFlags(new Required()),
            (new StringField('title', 'title'))->addFlags(new ApiAware()),
            (new StringField('first_name', 'firstName'))->addFlags(new ApiAware()),
            (new StringField('last_name', 'lastName'))->addFlags(new ApiAware()),
            (new StringField('company', 'company'))->addFlags(new ApiAware()),
            (new StringField('department', 'department'))->addFlags(new ApiAware()),
            (new StringField('vat_id', 'vatId'))->addFlags(new ApiAware()),

            (new StringField('street', 'street'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new StringField('zipcode', 'zipcode'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new StringField('city', 'city'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new FkField('country_id', 'countryId', CountryDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('country_state_id', 'countryStateId', CountryStateDefinition::class))->addFlags(new ApiAware()),

            (new StringField('phone_number', 'phoneNumber'))->addFlags(new ApiAware()),
            (new StringField('additional_address_line1', 'additionalAddressLine1'))->addFlags(new ApiAware()),
            (new StringField('additional_address_line2', 'additionalAddressLine2'))->addFlags(new ApiAware()),

            new CreatedAtField(),
            new UpdatedAtField(),

            # --------------------------------------------------------------------------------------------------------------------------

            (new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, 'id', true))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('countryState', 'country_state_id', CountryStateDefinition::class, 'id', true))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('salutation', 'salutation_id', SalutationDefinition::class, 'id', true))->addFlags(new ApiAware()),

            new ManyToOneAssociationField('subscription', 'subscription_id', SubscriptionDefinition::class, 'id', false),
            new OneToOneAssociationField('billingSubscription', 'id', 'billing_address_id', SubscriptionDefinition::class, false),
            new OneToOneAssociationField('shippingSubscription', 'id', 'shipping_address_id', SubscriptionDefinition::class, false),

        ]);
    }
}
