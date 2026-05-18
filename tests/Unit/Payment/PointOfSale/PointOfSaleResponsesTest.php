<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\PointOfSale;

use Mollie\Shopware\Component\Mollie\TerminalCollection;
use Mollie\Shopware\Component\Payment\PointOfSale\Route\ListTerminalsResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListTerminalsResponse::class)]
final class PointOfSaleResponsesTest extends TestCase
{
    public function testListTerminalsResponseStoresTerminals(): void
    {
        $terminals = new TerminalCollection();

        $response = new ListTerminalsResponse($terminals);

        $this->assertSame($terminals, $response->getTerminals());
    }
}
