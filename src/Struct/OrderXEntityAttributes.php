<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Struct;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class OrderXEntityAttributes
{
    /**
     * @var Entity
     */
    protected $entity;
    /**
     * @var string
     */
    private $mollieOrderLineID;

    public function __construct(Entity $entity)
    {
        $this->entity = $entity;

        $this->mollieOrderLineID = $this->getCustomFieldValue($entity, 'order_line_id');
    }

    /**
     * @return string
     */
    public function getMollieOrderLineID(): string
    {
        return $this->mollieOrderLineID;
    }

    /**
     * Somehow there are 2 custom fields? in payload and custom fields?
     * ....mhm...lets test always both
     * @param Entity $entity
     * @param string $keyName
     * @return string
     */
    protected function getCustomFieldValue(Entity $entity, string $keyName): string
    {
        $foundValue = '';

        # ---------------------------------------------------------------------------
        # search in custom fields

        if (method_exists($entity, 'getCustomFields')) {

            # check if we have customFields
            $customFields = $entity->getCustomFields();

            if ($customFields !== null) {
                # ---------------------------------------------------------------------------
                # search in new structure
                $fullKey = 'mollie_payments_product_' . $keyName;
                $foundValue = (array_key_exists($fullKey, $customFields)) ? (string)$customFields[$fullKey] : '';

                # old structure
                # check if we have a mollie entry
                if ($foundValue === '' && array_key_exists('mollie_payments', $customFields)) {
                    # load the mollie entry
                    $mollieData = $customFields['mollie_payments'];
                    # assign our value if we have it
                    $foundValue = (array_key_exists($keyName, $mollieData)) ? (string)$mollieData[$keyName] : '';
                }
            }
        }

        return $foundValue;
    }
}
