<?php

namespace MolliePayments\Tests\Struct\Attribute;

use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Kiener\MolliePayments\Struct\Attribute\AttributeCollection;
use Kiener\MolliePayments\Struct\Attribute\AttributeStruct;
use MolliePayments\Tests\Fakes\Attribute\FakeAttributeCollection;
use MolliePayments\Tests\Fakes\Attribute\FakeEntityAttributeStruct;
use MolliePayments\Tests\Fakes\FakeEntity;
use MolliePayments\Tests\Fakes\FakeEntityWithoutCustomFields;
use PHPUnit\Framework\TestCase;

class EntityAttributeStructTest extends TestCase
{
    public function testExceptionIsThrownIfEntityDoesNotSupportCustomFields()
    {
        $this->expectException(\Exception::class);

        new FakeEntityAttributeStruct(new FakeEntityWithoutCustomFields());
    }

    public function testAttributesAreEmptyWithNoCustomFields()
    {
        $entity1 = new FakeEntity();

        $attr1 = new FakeEntityAttributeStruct($entity1);
        $this->assertEquals([], $attr1->getVars());

        //===============================================//

        $entity2 = new FakeEntity();
        $entity2->setCustomFields([
            'foo' => 'bar'
        ]);

        $attr2 = new FakeEntityAttributeStruct($entity2);
        $this->assertEquals([], $attr2->getVars());
    }

    public function testMollieAttributesAreSet()
    {
        $entity = new FakeEntity();
        $entity->setCustomFields([
            CustomFieldsInterface::MOLLIE_KEY => [
                'string' => 'foo',
                'int' => 123,
                'bool' => true
            ]
        ]);

        $attr = new FakeEntityAttributeStruct($entity);

        $this->assertEquals('foo', $attr->getString());
        $this->assertEquals(123, $attr->getInt());
        $this->assertEquals(true, $attr->getBool());
    }

    public function testTranslatedEntity()
    {
        $entity = new FakeEntity();
        $entity->setCustomFields([
            CustomFieldsInterface::MOLLIE_KEY => [
                'string' => 'bar',
                'int' => 123,
                'bool' => true
            ]
        ]);
        $entity->setTranslated([
            'customFields' => [
                CustomFieldsInterface::MOLLIE_KEY => [
                    'string' => 'bar',
                    'int' => 123,
                    'bool' => true,
                    'camelCaseAttribute' => 'camel',
                    'snake_case_attribute' => 'snake'
                ]
            ]
        ]);

        $attr = new FakeEntityAttributeStruct($entity);

        $this->assertEquals('camel', $attr->getCamelCaseAttribute());
        $this->assertEquals('snake', $attr->getSnakeCaseAttribute());
    }

    public function testEntityWithCollectionInCustomFields()
    {
        $entity = new FakeEntity();
        $entity->setCustomFields([
            CustomFieldsInterface::MOLLIE_KEY => [
                'collection' => [
                    'foo' => [
                        'string' => 'foo',
                        'int' => 123,
                    ],
                    'bar' => [
                        'bool' => false,
                        'camelCaseAttribute' => 'camel'
                    ]
                ]
            ]
        ]);

        $attr = new FakeEntityAttributeStruct($entity);

        $this->assertInstanceOf(AttributeCollection::class, $attr->getCollection());

        /** @var FakeAttributeCollection $collection */
        $collection = $attr->getCollection();

        $this->assertTrue($collection->has('foo'));
        $this->assertTrue($collection->has('bar'));

        $this->assertInstanceOf(AttributeStruct::class, $collection->getStructForFakeId('foo'));
        $this->assertInstanceOf(AttributeStruct::class, $collection->getStructForFakeId('bar'));

        $foo = $collection->getStructForFakeId('foo');
        $this->assertEquals('foo', $foo->getString());
        $this->assertEquals(123, $foo->getInt());
        $this->assertEquals(null, $foo->getBool());

        $bar = $collection->getStructForFakeId('bar');
        $this->assertEquals(null, $bar->getString());
        $this->assertEquals(false, $bar->getBool());
        $this->assertEquals('camel', $bar->getCamelCaseAttribute());
    }

    public function testConvertToMollieArray()
    {
        $customFields = [
            CustomFieldsInterface::MOLLIE_KEY => [
                'string' => 'bar',
                'int' => 123,
                'bool' => true,
                'camelCaseAttribute' => 'camel',
                'snake_case_attribute' => 'snake',
                'collection' => [
                    'foo' => [
                        'string' => 'foo',
                        'int' => 123,
                    ],
                    'bar' => [
                        'bool' => false,
                        'camelCaseAttribute' => 'camel'
                    ]
                ]
            ]
        ];

        $entity = new FakeEntity();
        $entity->setCustomFields($customFields);

        $attr = new FakeEntityAttributeStruct($entity);

        $this->assertEquals($customFields, $attr->toMollieCustomFields());
    }
}
