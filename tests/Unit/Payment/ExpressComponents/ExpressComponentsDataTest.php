<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents;

use Mollie\Shopware\Component\Payment\ExpressComponents\ExpressComponentsData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExpressComponentsData::class)]
final class ExpressComponentsDataTest extends TestCase
{
    public function testDefaultsWithoutASession(): void
    {
        $data = new ExpressComponentsData(true, ['cart']);

        $this->assertTrue($data->isEnabled());
        $this->assertSame(['cart'], $data->getRestrictions());
        $this->assertSame('', $data->getSessionId());
        $this->assertSame('', $data->getClientAccessToken());
    }

    public function testWithASession(): void
    {
        $data = new ExpressComponentsData(true, [], 'sess_123', 'token');

        $this->assertSame('sess_123', $data->getSessionId());
        $this->assertSame('token', $data->getClientAccessToken());
    }
}
