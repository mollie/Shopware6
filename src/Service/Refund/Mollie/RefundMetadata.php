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
     * @param array<mixed> $metadata
     * @return RefundMetadata
     */
    public static function fromArray(array $metadata): RefundMetadata
    {
        $type = (string)$metadata['type'];
        $composition = (isset($metadata['composition'])) ? (array)$metadata['composition'] : [];

        $items = [];

        foreach ($composition as $compItem) {

            $items[] = new RefundItem(
                $compItem['swLineId'],
                $compItem['mollieLineId'],
                $compItem['swReference'],
                $compItem['quantity'],
                $compItem['amount']
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

    /**
     * @return string
     */
    public function toString(): string
    {
        $data = [
            'type' => $this->type,
        ];

        foreach ($this->items as $item) {

            if ($item->getQuantity() <= 0) {
                continue;
            }

            $data['composition'][] = [
                'swLineId' => $item->getShopwareLineID(),
                'mollieLineId' => $item->getMollieLineID(),
                'swReference' => $item->getShopwareReference(),
                'quantity' => $item->getQuantity(),
                'amount' => $item->getAmount(),
            ];
        }

        return json_encode($data);
    }

}