<?php

namespace Kiener\MolliePayments\Struct\Attribute;

use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

abstract class EntityAttributeStruct extends AttributeStruct
{
    private const ORIGINAL_ENTITY = 'originalEntity';

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

        /** TODO 003: Entities always have getTranslated.
         * If this entity is translatable, for example, product or category, then a method getTranslated is available.
         * Use the custom fields from this translated array instead of the regular ones.
         *
         * The getTranslated array contains all the translated fields of this entity, merged on top of the data
         * in the same fields from the default system language.
         */
        $customFields = method_exists($entity, 'getTranslated')
            ? $entity->getTranslated()['customFields']
            : $entity->getCustomFields();

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
        // TODO 002 Use original entity to determine which keys come from the system default language and were not changed
        // TODO 002 We dont want to return those in the array.
        return [CustomFieldsInterface::MOLLIE_KEY => $this->toArray()];
    }
}
