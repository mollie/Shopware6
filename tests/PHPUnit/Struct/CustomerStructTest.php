<?php declare(strict_types=1);

namespace MolliePayments\Tests\Struct;

use Kiener\MolliePayments\Struct\CustomerStruct;
use PHPUnit\Framework\TestCase;

class CustomerStructTest extends TestCase
{
    /**
     * Tests whether the magic set changes keys from snake case to camelcase, and if values set with a snake case key can be read using the camelcased getter methods.
     *
     * @param string|null $snake_case_ideal_issuer
     * @param string|null $expectedCamelIdealIssuer
     * @dataProvider idealIssuerTestData
     */
    public function testSnakeToCamelKeyMagicSet(?string $snake_case_ideal_issuer, ?string $expectedCamelIdealIssuer)
    {
        $struct = new CustomerStruct();

        $struct->assign([
            'preferred_ideal_issuer' => $snake_case_ideal_issuer
        ]);

        $actualValue = $struct->getPreferredIdealIssuer();
        $this->assertSame($expectedCamelIdealIssuer, $actualValue);
    }

    /**
     * @param string|null $customerId
     * @param string|null $expectedCustomerId
     * @dataProvider legacyCustomerIdTestData
     */
    public function testGetLegacyCustomerId(?string $customerId, ?string $expectedCustomerId)
    {
        $struct = new CustomerStruct();

        $struct->setLegacyCustomerId($customerId);
        $actualValue = $struct->getLegacyCustomerId();
        $this->assertSame($expectedCustomerId, $actualValue);
    }


    /**
     * @param $testData
     * @param string $profileId
     * @param bool $testMode
     * @param string $expectedCustomerId
     * @dataProvider mollieCustomerIdsTestData
     */
    public function testGetCustomerId(
        $testData,
        string $profileId,
        bool $testMode,
        string $expectedCustomerId
    )
    {
        $struct = new CustomerStruct();
        $struct->assign($testData);

        $actualValue = $struct->getCustomerId($profileId, $testMode);
        $this->assertSame($expectedCustomerId, $actualValue);
    }

    public function legacyCustomerIdTestData()
    {
        return [
            'legacy customer id can be set' => ['cst_123', 'cst_123'],
            'legacy customer id can be null' => [null, null],
            'legacy customer id can be empty' => ['', ''],
        ];
    }

    public function idealIssuerTestData()
    {
        return [
            'ideal issuer can be set' => ['ideal_INGBNL2A', 'ideal_INGBNL2A'],
            'ideal issuer id can be null' => [null, null],
            'ideal issuer id can be empty' => ['', ''],
        ];
    }

    public function mollieCustomerIdsTestData()
    {
        return [
            'profileId foo, live' => [
                $this->customerIds(),
                'foo',
                false,
                'cst_123'
            ],
            'profileId foo, test' => [
                $this->customerIds(),
                'foo',
                true,
                'cst_321'
            ],
            'profileId bar, live' => [
                $this->customerIds(),
                'bar',
                false,
                'cst_789'
            ],
            'profileId bar, test' => [
                $this->customerIds(),
                'bar',
                true,
                'cst_987'
            ],
            'profileId baz, live' => [
                $this->customerIds(),
                'baz',
                false,
                'cst_456'
            ],
            'profileId baz, test' => [
                $this->customerIds(),
                'baz',
                true,
                'cst_654'
            ],
            'profileId doesn\'t exist, live' => [
                $this->customerIds(),
                'fizz',
                false,
                ''
            ],
            'profileId doesn\'t exist, test' => [
                $this->customerIds(),
                'fizz',
                true,
                ''
            ],
        ];
    }

    private function customerIds()
    {
        return [
            'customer_ids' => [
                'foo' => [
                    'live' => 'cst_123',
                    'test' => 'cst_321',
                ],
                'bar' => [
                    'live' => 'cst_789',
                    'test' => 'cst_987',
                ],
                'baz' => [
                    'live' => 'cst_456',
                    'test' => 'cst_654',
                ]
            ]
        ];
    }
}
