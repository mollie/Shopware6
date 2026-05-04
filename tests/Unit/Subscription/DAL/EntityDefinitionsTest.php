<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\DAL;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressDefinition;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionHistory\SubscriptionHistoryCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionHistory\SubscriptionHistoryDefinition;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionHistory\SubscriptionHistoryEntity;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionDefinition;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[CoversClass(SubscriptionDefinition::class)]
#[CoversClass(SubscriptionAddressDefinition::class)]
#[CoversClass(SubscriptionHistoryDefinition::class)]
final class EntityDefinitionsTest extends TestCase
{
    private const FIELDS_COVERED_BY_PARENT_ENTITY = ['id', 'createdAt', 'updatedAt'];

    /**
     * @param class-string<EntityDefinition> $definitionClass
     */
    #[DataProvider('definitionProvider')]
    public function testGetEntityNameMatchesEntityNameConstant(string $definitionClass, string $expectedEntityName): void
    {
        $definition = new $definitionClass();

        $this->assertSame($expectedEntityName, $definition->getEntityName());
        $this->assertSame($expectedEntityName, $definitionClass::ENTITY_NAME);
    }

    /**
     * @param class-string<EntityDefinition> $definitionClass
     */
    #[DataProvider('definitionProvider')]
    public function testAutoconfigureTagDeclaresEntityNameMatchingEntityConstant(string $definitionClass, string $expectedEntityName): void
    {
        $reflection = new \ReflectionClass($definitionClass);
        $attributes = $reflection->getAttributes(AutoconfigureTag::class);

        $found = false;
        foreach ($attributes as $attribute) {
            $args = $attribute->getArguments();
            if (($args[0] ?? null) !== 'shopware.entity.definition') {
                continue;
            }
            if (($args[1]['entity'] ?? null) === $expectedEntityName) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, sprintf(
            'Definition %s must carry #[AutoconfigureTag("shopware.entity.definition", ["entity" => "%s"])]',
            $definitionClass,
            $expectedEntityName
        ));
    }

    /**
     * @param class-string<EntityDefinition> $definitionClass
     * @param class-string $expectedEntityClass
     */
    #[DataProvider('definitionProvider')]
    public function testGetEntityClassReturnsExpectedEntityClass(string $definitionClass, string $expectedEntityName, string $expectedEntityClass): void
    {
        $definition = new $definitionClass();

        $this->assertSame($expectedEntityClass, $definition->getEntityClass());
    }

    /**
     * @param class-string<EntityDefinition> $definitionClass
     * @param class-string $expectedCollectionClass
     */
    #[DataProvider('definitionProvider')]
    public function testGetCollectionClassReturnsExpectedCollectionClass(
        string $definitionClass,
        string $expectedEntityName,
        string $expectedEntityClass,
        string $expectedCollectionClass
    ): void {
        $definition = new $definitionClass();

        $this->assertSame($expectedCollectionClass, $definition->getCollectionClass());
    }

    /**
     * @param class-string<EntityDefinition> $definitionClass
     */
    #[DataProvider('definitionProvider')]
    public function testDefineFieldsReturnsAFieldCollection(string $definitionClass): void
    {
        $fields = $this->invokeDefineFields(new $definitionClass());

        $this->assertInstanceOf(FieldCollection::class, $fields);
        $this->assertGreaterThan(0, $fields->count());
    }

    /**
     * Every non-runtime field declared in defineFields must have a matching getter
     * and setter on the entity class. This catches drift between schema and entity
     * before `dal:validate` ever runs.
     *
     * @param class-string<EntityDefinition> $definitionClass
     * @param class-string $expectedEntityClass
     */
    #[DataProvider('definitionProvider')]
    public function testEveryDeclaredFieldHasGetterAndSetterOnEntity(string $definitionClass, string $expectedEntityName, string $expectedEntityClass): void
    {
        $fields = $this->invokeDefineFields(new $definitionClass());
        $entityReflection = new \ReflectionClass($expectedEntityClass);

        foreach ($fields as $field) {
            if (! $field instanceof Field) {
                continue;
            }

            $propertyName = $field->getPropertyName();
            if (in_array($propertyName, self::FIELDS_COVERED_BY_PARENT_ENTITY, true)) {
                continue;
            }
            if ($field->getFlag(Runtime::class) instanceof Runtime) {
                continue;
            }

            $getter = 'get' . ucfirst($propertyName);
            $setter = 'set' . ucfirst($propertyName);

            $this->assertTrue(
                $entityReflection->hasMethod($getter),
                sprintf('Entity %s is missing %s() for field "%s" declared in %s', $expectedEntityClass, $getter, $propertyName, $definitionClass)
            );
            $this->assertTrue(
                $entityReflection->hasMethod($setter),
                sprintf('Entity %s is missing %s() for field "%s" declared in %s', $expectedEntityClass, $setter, $propertyName, $definitionClass)
            );
        }
    }

    /**
     * @return array<string,array{0:class-string<EntityDefinition>,1:string,2:class-string,3:class-string}>
     */
    public static function definitionProvider(): array
    {
        return [
            'subscription' => [
                SubscriptionDefinition::class,
                SubscriptionDefinition::ENTITY_NAME,
                SubscriptionEntity::class,
                SubscriptionCollection::class,
            ],
            'subscription-address' => [
                SubscriptionAddressDefinition::class,
                SubscriptionAddressDefinition::ENTITY_NAME,
                SubscriptionAddressEntity::class,
                SubscriptionAddressCollection::class,
            ],
            'subscription-history' => [
                SubscriptionHistoryDefinition::class,
                SubscriptionHistoryDefinition::ENTITY_NAME,
                SubscriptionHistoryEntity::class,
                SubscriptionHistoryCollection::class,
            ],
        ];
    }

    private function invokeDefineFields(EntityDefinition $definition): FieldCollection
    {
        $reflection = new \ReflectionMethod($definition, 'defineFields');
        $reflection->setAccessible(true);

        /** @var FieldCollection $fields */
        return $reflection->invoke($definition);
    }
}
