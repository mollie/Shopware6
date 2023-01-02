<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Struct\MollieApi;

use Mollie\Api\Resources\OrderLine;

final class OrderLineMetaDataStruct
{
    private const TYPE_ROUNDING = 'rounding';
    private const TYPE_LINE_ITEM = 'lineItem';
    /**
     * @var string
     */
    private $id;
    /**
     * @var int
     */
    private $quantity;
    /**
     * @var float
     */
    private $amount;

    /**
     * @var string
     */
    private $type;

    public function __construct(OrderLine $orderLine)
    {
        $this->id = $orderLine->id;
        $this->quantity = (int)$orderLine->quantity;
        $this->type = $this->getTypeFromMetaData($orderLine->metadata, self::TYPE_LINE_ITEM);
        $this->amount = (float)$orderLine->totalAmount->value;
    }

    private function getTypeFromMetaData(\stdClass $metaData, string $default): string
    {
        if (property_exists($metaData, 'type') === false) {
            return $default;
        }
        return (string)$metaData->type;
    }

    public function isRoundingItem(): bool
    {
        return $this->type === self::TYPE_ROUNDING;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getId(): string
    {
        return $this->id;
    }
}
