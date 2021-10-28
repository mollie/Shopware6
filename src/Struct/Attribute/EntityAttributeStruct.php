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
        if(!method_exists($entity, 'getCustomFields')) {
            throw new \Exception('Entity does not contain custom fields');
        }

        $customFields = method_exists($entity, 'getTranslated')
            ? $entity->getTranslated()['customFields']
            : $entity->getCustomFields();

        if(empty($customFields)) {
            return;
        }

        if (!array_key_exists(CustomFieldsInterface::MOLLIE_KEY, $customFields)) {
            return;
        }

        $attributes = $customFields[CustomFieldsInterface::MOLLIE_KEY];

        parent::__construct($attributes);
    }

    /**
     * @return array
     */
    public function toMollieCustomFields(): array
    {
        return [CustomFieldsInterface::MOLLIE_KEY => $this->toArray()];
    }
}
