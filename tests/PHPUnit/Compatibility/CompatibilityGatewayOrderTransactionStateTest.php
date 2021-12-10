<?php

namespace MolliePayments\Tests\Compatibility;

use Kiener\MolliePayments\Compatibility\Gateway\CompatibilityGateway;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;

class CompatibilityGatewayOrderTransactionStateTest extends TestCase
{
    private $salesChannelContextService;


    protected function setUp(): void
    {
        $this->salesChannelContextService = $this->createMock(SalesChannelContextService::class);
    }

    /**
     * @param $swVersion
     * @param $expectedState
     * @dataProvider getChargebackStateTestData
     */
    public function testGetChargebackState($swVersion, $expectedState) {
        $compatibilityGateway = new CompatibilityGateway($swVersion, $this->salesChannelContextService);

        $actualState = $compatibilityGateway->getChargebackOrderTransactionState();

        $this->assertEquals($expectedState, $actualState);
    }

    public function getChargebackStateTestData()
    {
        return [
            'Chargeback state in Shopware 6.2.3 and higher' => ['6.2.3', 'chargeback'],
            'Chargeback state in Shopware 6.2.2 and lower' => ['6.2.2', 'in_progress'],
        ];
    }
}
