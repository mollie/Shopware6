<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Components\ApplePayDirect\Gateways;


use Kiener\MolliePayments\Components\ApplePayDirect\Gateways\ApplePayValidationUrlAllowListGateway;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ApplePayValidationUrlAllowListGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        $this->service = $this->createMock(SystemConfigService::class);
        $this->gateway = new ApplePayValidationUrlAllowListGateway($this->service);
    }

    public function testProvidesEmptyAllowList(): void
    {
        $this->service->expects($this->once())->method('get')->willReturn('');
        $allowList = $this->gateway->getAllowList();

        $this->assertTrue($allowList->isEmpty());
    }

    public function testProvidesAllowList(): void
    {
        $allowListString = 'https://example.com,https://example-url.org';
        $this->service->expects($this->once())->method('get')->willReturn($allowListString);
        $allowList = $this->gateway->getAllowList();

        $this->assertFalse($allowList->isEmpty());
        $this->assertCount(2, $allowList);
    }
}