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
}
