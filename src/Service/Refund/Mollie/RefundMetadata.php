<?php

namespace Kiener\MolliePayments\Service\Refund\Mollie;

use Kiener\MolliePayments\Service\Refund\Item\RefundItem;

class RefundMetadata
{

    /**
     * @var string
     */
    private $type;

    /**
     * @var RefundItem[]
     */
    private $items;



    /**
     * @param string $type
     * @param RefundItem[] $items
     */
    public function __construct(string $type, array $items)
    {
        $this->type = $type;
        $this->items = $items;
    }

    /**
     * @param \stdClass $metadata
     * @return RefundMetadata
     */
    public static function fromArray(\stdClass $metadata): RefundMetadata
    {
        $type = (string)$metadata->type;
        $composition = property_exists($metadata, 'composition') ? $metadata->composition : [];

        $items = [];

        foreach ($composition as $compItem) {
            $items[] = new RefundItem(
                $compItem['mollieLineId'],
                (string)$compItem['swReference'],
                $compItem['quantity'],
                $compItem['amount'],
                $compItem['swLineId'],
                $compItem['swLineVersionId']
            );
        }

        return new RefundMetadata($type, $items);
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return RefundItem[]
     */
    public function getComposition(): array
    {
        return $this->items;
    }
}
