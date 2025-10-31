<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\FlowBuilder\Event\EventData;

use Shopware\Core\Framework\Event\EventData\EventDataType;

final class PaymentType implements EventDataType
{
    public const TYPE = 'payment';
    private array $data;

    public function __construct()
    {
        $this->data = [
            'id' => 'tr_123456',
            'status' => 'paid'
        ];
    }

    public function toArray(): array
    {
        return [
            'type' => self::TYPE,
            'data' => $this->data,
        ];
    }
}
