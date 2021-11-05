<?php

namespace Kiener\MolliePayments\Struct\Attribute;

use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Struct;

abstract class AttributeCollection extends Collection
{
    public function getElements(): array
    {
        $data = [];

        foreach(parent::getElements() as $key => $value) {
            if(in_array($key, ['extensions'])) {
                continue;
            }

            if($value instanceof Collection) {
                $data[$key] = $value->getElements();
                continue;
            }

            if($value instanceof Struct) {
                $data[$key] = $value->getVars();
                continue;
            }

            $data[$key] = $value;
        }

        return $data;
    }
}
