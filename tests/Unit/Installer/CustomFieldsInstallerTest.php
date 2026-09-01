<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Installer;

use Mollie\Shopware\Component\Installer\CustomFieldsInstaller;
use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Mollie\VoucherCategory;
use Mollie\Shopware\Unit\Fake\FakeEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;

#[CoversClass(CustomFieldsInstaller::class)]
final class CustomFieldsInstallerTest extends TestCase
{
    private FakeEntityRepository $customFieldSetRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->customFieldSetRepository = new FakeEntityRepository(new CustomFieldSetDefinition());
        $this->context = Context::createDefaultContext();

        for ($i = 0; $i < 2; ++$i) {
            $this->customFieldSetRepository->entityWrittenContainerEvents[] = new EntityWrittenContainerEvent(
                $this->context,
                new NestedEventCollection(),
                []
            );
        }
    }

    public function testProductFieldSetIsAttachedToProducts(): void
    {
        $fieldSet = $this->installedProductFieldSet();

        $this->assertSame('mollie_payments_product', $fieldSet['name']);
        $this->assertTrue($fieldSet['active']);
        $this->assertSame('product', $fieldSet['relations'][0]['entityName']);
    }

    public function testAddressFieldSetIsAttachedToCustomerAddresses(): void
    {
        $fieldSet = $this->installedAddressFieldSet();

        $this->assertSame('mollie_payments_address', $fieldSet['name']);
        $this->assertTrue($fieldSet['active']);
        $this->assertSame('customer_address', $fieldSet['relations'][0]['entityName']);
    }

    public function testProductFieldNamesStayStableBecauseMerchantDataIsStoredUnderThem(): void
    {
        $fieldSet = $this->installedProductFieldSet();

        $this->assertSame(
            [
                'mollie_payments_product_voucher_type',
                'mollie_payments_product_subscription_enabled',
                'mollie_payments_product_subscription_interval',
                'mollie_payments_product_subscription_interval_unit',
                'mollie_payments_product_subscription_repetition',
                'mollie_payments_product_subscription_allow_onetime',
            ],
            array_column($fieldSet['customFields'], 'name')
        );
    }

    public function testFieldSetIdsStayStableSoAnUpdateNeverCreatesASecondFieldSet(): void
    {
        $this->assertSame('f2acb41af0be41638540b31917007fa3', $this->installedProductFieldSet()['id']);
        $this->assertSame('a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4', $this->installedAddressFieldSet()['id']);
    }

    public function testEveryOfferedVoucherTypeIsOneMollieAccepts(): void
    {
        $voucherField = $this->productCustomField('mollie_payments_product_voucher_type');
        $acceptedValues = array_map(static function (VoucherCategory $category): string {
            return $category->value;
        }, VoucherCategory::cases());

        foreach ($voucherField['config']['options'] as $option) {
            $this->assertContains($option['value'], $acceptedValues);
        }
    }

    public function testTheIntervalUnitDropdownOffersExactlyTheUnitsTheSubscriptionEngineUnderstands(): void
    {
        $unitField = $this->productCustomField('mollie_payments_product_subscription_interval_unit');
        $supportedUnits = array_map(static function (IntervalUnit $unit): string {
            return $unit->value;
        }, IntervalUnit::cases());

        $this->assertSame($supportedUnits, array_column($unitField['config']['options'], 'value'));
    }

    public function testProductFieldsAreExposedToTheCartSoTheCheckoutCanReadThem(): void
    {
        $fieldSet = $this->installedProductFieldSet();

        foreach ($fieldSet['customFields'] as $customField) {
            $this->assertTrue($customField['allowCartExpose'], sprintf('%s is not exposed to the cart', $customField['name']));
        }
    }

    public function testExpressAddressFieldUsesTheKeyTheExpressCheckoutWritesTo(): void
    {
        $addressField = $this->installedAddressFieldSet()['customFields'][0];

        $this->assertSame(Address::CUSTOM_FIELDS_KEY, $addressField['name']);
    }

    public function testExpressAddressFieldIsWritableByTheCustomerSoTheStoreApiCanFillIt(): void
    {
        $addressField = $this->installedAddressFieldSet()['customFields'][0];

        $this->assertTrue($addressField['allowCustomerWrite']);
    }

    public function testEveryProductFieldIsActive(): void
    {
        $fieldSet = $this->installedProductFieldSet();

        foreach ($fieldSet['customFields'] as $customField) {
            $this->assertTrue($customField['active'], sprintf('%s is not active', $customField['name']));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function productCustomField(string $name): array
    {
        foreach ($this->installedProductFieldSet()['customFields'] as $customField) {
            if ($customField['name'] === $name) {
                return $customField;
            }
        }

        self::fail(sprintf('Custom field "%s" was not installed', $name));
    }

    /**
     * @return array<string, mixed>
     */
    private function installedProductFieldSet(): array
    {
        return $this->install()[0][0];
    }

    /**
     * @return array<string, mixed>
     */
    private function installedAddressFieldSet(): array
    {
        return $this->install()[1][0];
    }

    /**
     * @return array<array<array<string, mixed>>>
     */
    private function install(): array
    {
        if (count($this->customFieldSetRepository->data) === 0) {
            $this->installer()->install($this->context);
        }

        return $this->customFieldSetRepository->data;
    }

    private function installer(): CustomFieldsInstaller
    {
        return new CustomFieldsInstaller($this->customFieldSetRepository);
    }
}
