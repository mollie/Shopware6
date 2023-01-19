<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Struct\Mandate;

use Shopware\Core\Framework\Struct\StructCollection;

/**
 * @extends StructCollection<MandateStruct>
 * @method void                add(MandateStruct $entity)
 * @method void                set(string $key, MandateStruct $entity)
 * @method MandateStruct[]    getIterator()
 * @method MandateStruct[]    getElements()
 * @method null|MandateStruct get(string $key)
 * @method null|MandateStruct first()
 * @method null|MandateStruct last()
 */
class MandateCollection extends StructCollection
{
    protected function getExpectedClass(): string
    {
        return MandateStruct::class;
    }
}
