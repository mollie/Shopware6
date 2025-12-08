<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Terminal;
use Mollie\Shopware\Component\Mollie\TerminalBrand;
use Mollie\Shopware\Component\Mollie\TerminalModel;
use Mollie\Shopware\Component\Mollie\TerminalStatus;
use PHPUnit\Framework\TestCase;

#[CoversClass(Terminal::class)]
final class TerminalTest extends TestCase
{
    public function testFromClientResponse(): void
    {
        $body = [
            'id' => '123',
            'description' => 'test',
            'currency' => 'eur',
            'status' => 'active',
            'brand' => 'PAX',
            'model' => 'A35',
            'serialNumber' => '123123123'
        ];

        $terminal = Terminal::fromClientResponse($body);

        $this->assertEquals('123', $terminal->getId());
        $this->assertEquals('test', $terminal->getDescription());
        $this->assertEquals('eur', $terminal->getCurrency());
        $this->assertEquals(TerminalStatus::ACTIVE, $terminal->getStatus());
        $this->assertEquals(TerminalBrand::PAX, $terminal->getBrand());
        $this->assertEquals(TerminalModel::A35, $terminal->getModel());
        $this->assertEquals('123123123', $terminal->getSerialNumber());
    }
}
