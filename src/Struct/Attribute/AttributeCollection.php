<?php

namespace Kiener\MolliePayments\Struct\Attribute;

use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Struct;

abstract class AttributeCollection extends Collection
{
    /**
     * Returns all elements of this collection
     *
     * @return array
     */
    public function getElements(): array
    {
        $data = [];

        foreach (parent::getElements() as $key => $value) {
            /**
             * If $value is a Collection, return the inner elements array
             */
            if ($value instanceof Collection) {
                $data[$key] = $value->getElements();
                continue;
            }

            /**
             * If $value is a Struct, return all the properties of the struct
             */
            if ($value instanceof Struct) {
                $data[$key] = $value->getVars();
                continue;
            }

            /**
             * Otherwise just set the value in our data array.
             */
            $data[$key] = $value;
        }

        return $data;
    }
}
