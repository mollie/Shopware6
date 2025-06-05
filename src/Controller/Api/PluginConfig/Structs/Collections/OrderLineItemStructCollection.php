<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\PluginConfig\Structs\Collections;

use Kiener\MolliePayments\Controller\Api\PluginConfig\Exceptions\LineItemNotPartOfCollectionConfigException;
use Kiener\MolliePayments\Controller\Api\PluginConfig\Structs\OrderLineItemStruct;
use Shopware\Core\Framework\Struct\StructCollection;

/**
 * @extends StructCollection<OrderLineItemStruct>
 */
class OrderLineItemStructCollection extends StructCollection
{
    /**
     * @param iterable<OrderLineItemStruct> $elements
     */
    final private function __construct(iterable $elements)
    {
        parent::__construct($elements);
    }

    public static function create(OrderLineItemStruct ...$structs): self
    {
        return new self($structs);
    }

    /**
     * @throws LineItemNotPartOfCollectionConfigException
     */
    public function getById(string $id): OrderLineItemStruct
    {
        /** @var OrderLineItemStruct $item */
        foreach ($this as $item) {
            if ($item->getId() === $id) {
                return $item;
            }
        }

        throw LineItemNotPartOfCollectionConfigException::create($id);
    }

    public function getRefundableQuantity(): int
    {
        $refundableQuantity = 0;
        /** @var OrderLineItemStruct $item */
        foreach ($this as $item) {
            $refundableQuantity += $item->getRefundableQuantity();
        }

        return $refundableQuantity;
    }
}
