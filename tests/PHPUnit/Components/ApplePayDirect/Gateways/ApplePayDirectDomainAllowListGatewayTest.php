<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Components\ApplePayDirect\Gateways;

use Kiener\MolliePayments\Components\ApplePayDirect\Gateways\ApplePayDirectDomainAllowListGateway;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ApplePayDirectDomainAllowListGatewayTest extends TestCase
{
    /**
     * @var MockObject|SettingsService
     */
    private $service;

    /**
     * @var ApplePayDirectDomainAllowListGateway
     */
    private $gateway;

    /**
     * @var Context|MockObject
     */
    private $context;

    /**
     * @var MockObject|SalesChannelContext
     */
    private $scContext;

    protected function setUp(): void
    {
        $this->service = $this->createMock(SettingsService::class);
        $this->gateway = new ApplePayDirectDomainAllowListGateway($this->service);
        $this->scContext = $this->createConfiguredMock(SalesChannelContext::class, [
            'getContext' => $this->context = $this->createMock(Context::class),
        ]);
    }

    public function testProvidesEmptyAllowList(): void
    {
        $struct = $this->createConfiguredMock(MollieSettingStruct::class, ['getApplePayDirectDomainAllowList' => '']);
        $this->service->expects($this->once())->method('getSettings')->willReturn($struct);
        $allowList = $this->gateway->getAllowList($this->scContext);

        $this->assertTrue($allowList->isEmpty());
    }

    public function testProvidesAllowList(): void
    {
        $allowListString = 'https://example.com,https://example-url.org';
        $struct = $this->createConfiguredMock(MollieSettingStruct::class, ['getApplePayDirectDomainAllowList' => $allowListString]);
        $this->service->expects($this->once())->method('getSettings')->willReturn($struct);

        $allowList = $this->gateway->getAllowList($this->scContext);

        $this->assertFalse($allowList->isEmpty());
        $this->assertCount(2, $allowList);
    }
}
