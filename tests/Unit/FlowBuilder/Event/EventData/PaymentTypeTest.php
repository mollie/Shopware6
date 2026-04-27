<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\FlowBuilder\Event\EventData;

use Mollie\Shopware\Component\FlowBuilder\Event\EventData\PaymentType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentType::class)]
final class PaymentTypeTest extends TestCase
{
    public function testToArrayReturnsCorrectStructure(): void
    {
        $type = new PaymentType();
        $array = $type->toArray();

        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('data', $array);
        $this->assertSame(PaymentType::TYPE, $array['type']);
        $this->assertIsArray($array['data']);
    }

    public function testTypeConstant(): void
    {
        $this->assertSame('payment', PaymentType::TYPE);
    }
}
