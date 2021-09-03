<?php

namespace MolliePayments\Tests\Service\MollieApi;

use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\MollieApi\MollieOrderCustomerEnricher;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Kiener\MolliePayments\Struct\CustomerStruct;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class MollieOrderCustomerEnricherTest extends TestCase
{

    /** @var MollieOrderCustomerEnricher */
    private $mollieOrderCustomerEnricher;

    public function setUp(): void
    {
        $customerStruct = new CustomerStruct();
        $customerStruct->setCustomerId('foo', 'bar', false);

        $customerService = $this->createConfiguredMock(CustomerService::class, [
            'getCustomerStruct' => $customerStruct
        ]);

        $this->mollieOrderCustomerEnricher = new MollieOrderCustomerEnricher($customerService);
    }

    public function testThatOrderDataIsEnrichedWithMollieCustomerId()
    {
        $expectedOrderData = [
            'payment' => [
                'customerId' => 'foo'
            ]
        ];

        $customer = $this->createConfiguredMock(CustomerEntity::class, [
            'getId' => 'fizz'
        ]);

        $settings = new MollieSettingStruct();
        $settings->setProfileId('bar');
        $settings->setTestMode(false);

        $salesChannelContext = $this->createConfiguredMock(SalesChannelContext::class, [
            'getContext' => $this->createMock(Context::class)
        ]);

        $actualOrderData = $this->mollieOrderCustomerEnricher->enrich([], $customer, $settings, $salesChannelContext);

        $this->assertSame($expectedOrderData, $actualOrderData);
    }
}
