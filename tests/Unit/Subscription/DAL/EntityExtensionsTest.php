<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\DAL;

use Mollie\Shopware\Component\Subscription\DAL\Country\CountryExtension;
use Mollie\Shopware\Component\Subscription\DAL\Country\CountryStateExtension;
use Mollie\Shopware\Component\Subscription\DAL\Currency\CurrencyExtension;
use Mollie\Shopware\Component\Subscription\DAL\Customer\CustomerExtension;
use Mollie\Shopware\Component\Subscription\DAL\Order\OrderExtension;
use Mollie\Shopware\Component\Subscription\DAL\Salutation\SalutationExtension;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressDefinition;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Salutation\SalutationDefinition;

#[CoversClass(CountryExtension::class)]
#[CoversClass(CountryStateExtension::class)]
#[CoversClass(CurrencyExtension::class)]
#[CoversClass(CustomerExtension::class)]
#[CoversClass(OrderExtension::class)]
#[CoversClass(SalutationExtension::class)]
final class EntityExtensionsTest extends TestCase
{
    /**
     * @param class-string<EntityExtension> $extensionClass
     * @param class-string $expectedDefinitionClass
     */
    #[DataProvider('extensionProvider')]
    public function testGetDefinitionClassReturnsExpectedDefinition(string $extensionClass, string $expectedDefinitionClass, string $expectedEntityName): void
    {
        $extension = new $extensionClass();

        $this->assertSame($expectedDefinitionClass, $extension->getDefinitionClass());
        $this->assertSame($expectedEntityName, $extension->getEntityName());
    }

    /**
     * @param class-string<EntityExtension> $extensionClass
     * @param class-string $expectedReferenceClass
     */
    #[DataProvider('extensionProvider')]
    public function testExtendFieldsAddsExpectedAssociation(
        string $extensionClass,
        string $expectedDefinitionClass,
        string $expectedEntityName,
        string $expectedPropertyName,
        string $expectedReferenceClass,
        bool $expectedCascadeDelete
    ): void {
        $extension = new $extensionClass();
        $collection = new FieldCollection();

        $extension->extendFields($collection);

        $field = $this->findAssociationField($collection, $expectedPropertyName);

        $this->assertInstanceOf(OneToManyAssociationField::class, $field);
        $this->assertSame($expectedReferenceClass, $field->getReferenceClass());
        $this->assertSame($expectedCascadeDelete, $field->getFlag(CascadeDelete::class) instanceof CascadeDelete);
    }

    /**
     * @return array<string,array{0:class-string,1:class-string,2:string,3:string,4:class-string,5:bool}>
     */
    public static function extensionProvider(): array
    {
        return [
            'country' => [
                CountryExtension::class,
                CountryDefinition::class,
                CountryDefinition::ENTITY_NAME,
                'subscriptionAddress',
                SubscriptionAddressDefinition::class,
                true,
            ],
            'country-state' => [
                CountryStateExtension::class,
                CountryStateDefinition::class,
                CountryStateDefinition::ENTITY_NAME,
                'subscriptionAddress',
                SubscriptionAddressDefinition::class,
                true,
            ],
            'currency' => [
                CurrencyExtension::class,
                CurrencyDefinition::class,
                CurrencyDefinition::ENTITY_NAME,
                'subscriptions',
                SubscriptionDefinition::class,
                true,
            ],
            'customer' => [
                CustomerExtension::class,
                CustomerDefinition::class,
                CustomerDefinition::ENTITY_NAME,
                'subscriptions',
                SubscriptionDefinition::class,
                true,
            ],
            'order' => [
                OrderExtension::class,
                OrderDefinition::class,
                OrderDefinition::ENTITY_NAME,
                'mollieSubscriptions',
                SubscriptionDefinition::class,
                false,
            ],
            'salutation' => [
                SalutationExtension::class,
                SalutationDefinition::class,
                SalutationDefinition::ENTITY_NAME,
                'subscriptionAddress',
                SubscriptionAddressDefinition::class,
                true,
            ],
        ];
    }

    private function findAssociationField(FieldCollection $collection, string $propertyName): ?OneToManyAssociationField
    {
        foreach ($collection as $field) {
            if ($field instanceof OneToManyAssociationField && $field->getPropertyName() === $propertyName) {
                return $field;
            }
        }

        return null;
    }
}
