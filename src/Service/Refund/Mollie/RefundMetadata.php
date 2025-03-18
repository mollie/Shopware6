<?php
declare(strict_types=1);

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
     * @param RefundItem[] $items
     */
    public function __construct(string $type, array $items)
    {
        $this->type = $type;
        $this->items = $items;
    }

    public static function fromArray(\stdClass $metadata): RefundMetadata
    {
        $type = (string) $metadata->type;
        $composition = property_exists($metadata, 'composition') ? $metadata->composition : [];

        $items = [];

        foreach ($composition as $compItem) {
            $items[] = new RefundItem(
                $compItem['mollieLineId'],
                (string) $compItem['swReference'],
                $compItem['quantity'],
                $compItem['amount'],
                $compItem['swLineId'],
                $compItem['swLineVersionId']
            );
        }

        return new RefundMetadata($type, $items);
    }

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
     * Used as storage payload inside the Mollie API database.
     */
    public function toMolliePayload(): string
    {
        $data = [
            'type' => $this->type,
        ];

        return (string) json_encode($data);
    }
}
