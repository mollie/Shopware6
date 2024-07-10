<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Components\ApplePayDirect\Gateways;


use Kiener\MolliePayments\Components\ApplePayDirect\Gateways\ApplePayDirectDomainAllowListGateway;
use Kiener\MolliePayments\Service\SettingsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ApplePayDirectDomainAllowListGatewayTest extends TestCase
{
    /**
     * @var SettingsService|MockObject
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
     * @var SalesChannelContext|MockObject
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
        $struct = $this->createMock(\Kiener\MolliePayments\Setting\MollieSettingStruct::class);
        $struct->{SettingsService::APPLE_PAY_DIRECT_DOMAIN_ALLOW_LIST} = '';
        $this->service->expects($this->once())->method('getSettings')->willReturn($struct);
        $allowList = $this->gateway->getAllowList($this->scContext);

        $this->assertTrue($allowList->isEmpty());
    }

    public function testProvidesAllowList(): void
    {
        $allowListString = 'https://example.com,https://example-url.org';
        $struct = $this->createMock(\Kiener\MolliePayments\Setting\MollieSettingStruct::class);
        $struct->{SettingsService::APPLE_PAY_DIRECT_DOMAIN_ALLOW_LIST} = $allowListString;
        $this->service->expects($this->once())->method('getSettings')->willReturn($struct);

        $allowList = $this->gateway->getAllowList($this->scContext);

        $this->assertFalse($allowList->isEmpty());
        $this->assertCount(2, $allowList);
    }
}