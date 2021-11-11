<?php

namespace Kiener\MolliePayments\Struct\Attribute;

use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

abstract class EntityAttributeStruct extends AttributeStruct
{
    /**
     * @param Entity $entity
     * @throws \Exception
     */
    public function __construct(Entity $entity)
    {
        /**
         * If this entity does not contain getCustomFields, we can't do anything, and we shouldn't even create
         * an attribute struct in the first place.
         */
        if(!method_exists($entity, 'getCustomFields')) {
            throw new \Exception('Entity does not contain custom fields');
        }

        /**
         * Use the custom fields from this translated array instead of the regular ones.
         *
         * The getTranslated array contains all the translated fields of this entity, merged on top of the data
         * in the same fields from the default system language.
         */
        $customFields = $entity->getTranslation('customFields') ?? $entity->getCustomFields();

        /**
         * If we don't have any custom fields, stop and return.
         */
        if(empty($customFields)) {
            return;
        }

        /**
         * Similarly, if the custom fields doesn't have our mollie_payments key, stop.
         */
        if (!array_key_exists(CustomFieldsInterface::MOLLIE_KEY, $customFields)) {
            return;
        }

        /**
         * Grab our mollie_payments key and use it to construct our attribute struct
         */
        $attributes = $customFields[CustomFieldsInterface::MOLLIE_KEY];

        parent::__construct($attributes);
    }

    /**
     * @return array<mixed>
     */
    public function toMollieCustomFields(): array
    {
        return [CustomFieldsInterface::MOLLIE_KEY => $this->toArray()];
    }
}
