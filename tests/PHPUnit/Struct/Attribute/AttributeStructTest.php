<?php declare(strict_types=1);

namespace MolliePayments\Tests\Struct\Attribute;

use MolliePayments\Tests\Fakes\Attribute\FakeAttributeStruct;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Struct\ArrayStruct;

class AttributeStructTest extends TestCase
{
    public function testAttributesAreSetCorrectly()
    {
        $existingData = [
            'string' => 'foo',
            'int' => 123,
            'bool' => true,
        ];

        $struct = new FakeAttributeStruct($existingData);

        $this->assertEquals($existingData['string'], $struct->getString());
        $this->assertEquals($existingData['int'], $struct->getInt());
        $this->assertEquals($existingData['bool'], $struct->getBool());
        $this->assertEquals(null, $struct->getCamelCaseAttribute());
        $this->assertEquals(null, $struct->getSnakeCaseAttribute());
    }

    /**
     * Tests whether getVars/toArray converts the struct properties back to their original array.
     */
    public function testAttributesAreConvertingBackToArray()
    {
        $existingData = [
            'string' => 'foo',
            'int' => 123,
            'bool' => true,
            'camelCaseAttribute' => 'bar',
            'snake_case_attribute' => 'baz',
        ];

        $struct = new FakeAttributeStruct($existingData);

        $this->assertEquals($existingData, $struct->getVars());
        $this->assertEquals($existingData, $struct->toArray());
    }

    public function testNulledAttributesAreAddedToArray()
    {
        $existingData = [
            'string' => 'foo',
            'int' => 123,
            'bool' => true,
        ];

        $struct = new FakeAttributeStruct($existingData);
        $struct->setString(null);

        $expectedData = [
            'string' => null,
            'int' => 123,
            'bool' => true,
        ];

        $this->assertEquals($expectedData, $struct->getVars());
        $this->assertEquals($expectedData, $struct->toArray());

        $this->assertNotEquals($existingData, $struct->getVars());
        $this->assertNotEquals($existingData, $struct->toArray());
    }

    public function testNewlySetAttributesAreAddedToArray()
    {
        $existingData = [
            'string' => 'foo',
            'int' => 123,
        ];

        $struct = new FakeAttributeStruct($existingData);
        $struct->setBool(false);

        $expectedData = [
            'string' => 'foo',
            'int' => 123,
            'bool' => false,
        ];

        $this->assertEquals($expectedData, $struct->getVars());
        $this->assertEquals($expectedData, $struct->toArray());

        $this->assertNotEquals($existingData, $struct->getVars());
        $this->assertNotEquals($existingData, $struct->toArray());
    }

    public function testDifferentCaseAttributesAreSet()
    {
        $existingData = [
            'camelCaseAttribute' => 'foo',
            'snake_case_attribute' => 'bar',
        ];

        $struct = new FakeAttributeStruct($existingData);

        $this->assertEquals('foo', $struct->getCamelCaseAttribute());
        $this->assertEquals('bar', $struct->getSnakeCaseAttribute());
    }

    public function testMergeTwoStructs()
    {
        $data1 = [
            'string' => 'foo',
            'int' => 123,
        ];

        $data2 = [
            'camelCaseAttribute' => 'foo',
            'snake_case_attribute' => 'bar',
        ];

        $struct1 = new FakeAttributeStruct($data1);
        $struct2 = new FakeAttributeStruct($data2);

        $struct1->merge($struct2);

        $expectedData = [
            'string' => 'foo',
            'int' => 123,
            'camelCaseAttribute' => 'foo',
            'snake_case_attribute' => 'bar',
        ];

        $this->assertNotEquals($data1, $struct1->getVars());
        $this->assertEquals($expectedData, $struct1->getVars());
    }

    public function testNonPropertiesAreStoredInExtension()
    {
        $existingData = [
            'thisPropertyDoesNotExist' => true,
        ];

        $struct = new FakeAttributeStruct($existingData);

        $this->assertInstanceOf(ArrayStruct::class, $struct->getExtension($struct::ADDITIONAL));

        /** @var ArrayStruct $additional */
        $additional = $struct->getExtension($struct::ADDITIONAL);

        $this->assertTrue($additional->has('thisPropertyDoesNotExist'));
        $this->assertEquals(true, $additional->get('thisPropertyDoesNotExist'));

        $this->assertEquals($existingData, $struct->getVars());
    }
}
