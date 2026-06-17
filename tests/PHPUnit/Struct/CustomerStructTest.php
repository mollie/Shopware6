<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Struct;

use Kiener\MolliePayments\Struct\CustomerStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CustomerStructTest extends TestCase
{
    /**
     * Tests whether the magic __set changes keys from snake case to camelcase, and if values set with a snake case key can be read using the camelcased getter methods.
     */
    public function testSnakeToCamelKeyMagicSet()
    {
        $struct = new CustomerStruct();

        $struct->assign([
            'customer_ids' => ['bar'],
        ]);

        $this->assertSame(['bar'], $struct->getCustomerIds());
    }

    /**
     * @param mixed $testData
     */
    #[DataProvider('mollieCustomerIdsTestData')]
    public function testGetCustomerId(
        $testData,
        string $profileId,
        bool $testMode,
        string $expectedCustomerId
    ) {
        $struct = new CustomerStruct();
        $struct->assign($testData);

        $actualValue = $struct->getCustomerId($profileId, $testMode);
        $this->assertSame($expectedCustomerId, $actualValue);
    }

    public static function mollieCustomerIdsTestData()
    {
        return [
            'profileId foo, live' => [
                self::customerIds(),
                'foo',
                false,
                'cst_123',
            ],
            'profileId foo, test' => [
                self::customerIds(),
                'foo',
                true,
                'cst_321',
            ],
            'profileId bar, live' => [
                self::customerIds(),
                'bar',
                false,
                'cst_789',
            ],
            'profileId bar, test' => [
                self::customerIds(),
                'bar',
                true,
                'cst_987',
            ],
            'profileId baz, live' => [
                self::customerIds(),
                'baz',
                false,
                'cst_456',
            ],
            'profileId baz, test' => [
                self::customerIds(),
                'baz',
                true,
                'cst_654',
            ],
            'profileId doesn\'t exist, live' => [
                self::customerIds(),
                'fizz',
                false,
                '',
            ],
            'profileId doesn\'t exist, test' => [
                self::customerIds(),
                'fizz',
                true,
                '',
            ],
        ];
    }

    /**
     * This test verifies that our custom fields array is correctly built
     * with all its data.
     */
    public function testCustomFieldsArray()
    {
        $struct = new CustomerStruct();

        $struct->setCustomerId('cst_1test', 'pfl_1', true);
        $struct->setCustomerId('cst_2live', 'pfl_2', false);
        $struct->setCustomerId('cst_3live', 'pfl_3', false);
        $struct->setCustomerId('cst_3test', 'pfl_3', true);

        $struct->setCreditCardToken('cc_123');

        $customFields = $struct->toCustomFieldsArray();

        $expected = [
            'mollie_payments' => [
                'customer_ids' => [
                    'pfl_1' => [
                        'live' => '',
                        'test' => 'cst_1test',
                    ],
                    'pfl_2' => [
                        'live' => 'cst_2live',
                        'test' => '',
                    ],
                    'pfl_3' => [
                        'live' => 'cst_3live',
                        'test' => 'cst_3test',
                    ],
                ],
                'credit_card_token' => 'cc_123',
            ],
        ];

        $this->assertEquals($expected, $customFields);
    }

    /**
     * This test verifies that our legacy customer id is correctly removed
     * and migrated to the new profile structure if the same id is being used.
     */
    public function testCustomFieldsArrayMigrateLegacyCustomerID()
    {
        $struct = new CustomerStruct();

        $struct->setLegacyCustomerId('cst_1test');

        $struct->setCustomerId('cst_1test', 'pfl_1', true);

        $customFields = $struct->toCustomFieldsArray();

        $expected = [
            'mollie_payments' => [
                'customer_ids' => [
                    'pfl_1' => [
                        'live' => '',
                        'test' => 'cst_1test',
                    ],
                ],
            ],
            'customer_id' => null,
        ];

        $this->assertEquals($expected, $customFields);
    }

    /**
     * This test verifies that we keep the legacy customer id entry if we
     * do not have an explicit entry for this id in one of our profiles.
     * We have to keep this for our history, because one day the correct profile will be used
     * where we need to ensure to REUSE this old customer id entry.
     */
    public function testCustomFieldsArrayKeepLegacyCustomerId()
    {
        $struct = new CustomerStruct();

        $struct->setLegacyCustomerId('cst_old_different');

        $struct->setCustomerId('cst_2test', 'pfl_2', true);

        $customFields = $struct->toCustomFieldsArray();

        $expected = [
            'customer_id' => 'cst_old_different',
            'mollie_payments' => [
                'customer_ids' => [
                    'pfl_2' => [
                        'live' => '',
                        'test' => 'cst_2test',
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $customFields);
    }

    private static function customerIds()
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
                ],
            ],
        ];
    }
}
