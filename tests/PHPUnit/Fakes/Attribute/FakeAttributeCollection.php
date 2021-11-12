<?php

namespace MolliePayments\Tests\Fakes\Attribute;

use Kiener\MolliePayments\Struct\Attribute\AttributeCollection;

class FakeAttributeCollection extends AttributeCollection
{
    public function getStructForFakeId(string $id): FakeAttributeStruct
    {
        if(!$this->has($id)) {
            return new FakeAttributeStruct();
        }

        return $this->get($id);
    }
}
