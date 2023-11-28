<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Service;

use Kiener\MolliePayments\Service\TrackingInfoStructFactory;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;

/**
 * @coversDefaultClass \Kiener\MolliePayments\Service\TrackingInfoStructFactory
 */
class TrackingInfoStructFactoryTest extends TestCase
{
    /**
     * @var TrackingInfoStructFactory
     */
    private $factory;

    public function setUp(): void
    {
        $this->factory = new TrackingInfoStructFactory();
    }


    public function testInfoStructCreatedByDelivery(): void
    {
        $expectedCode = '1234';
        $expectedCarrier = 'Test carrier';
        $expectedUrl = 'https://test.foo?code=1234';
        $deliveryEntity = new OrderDeliveryEntity();
        $deliveryEntity->setUniqueIdentifier('testDelivery');
        $deliveryEntity->setTrackingCodes([
            $expectedCode
        ]);

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setName($expectedCarrier);
        $shippingMethod->setUniqueIdentifier('testShippingMethod');
        $shippingMethod->setTrackingUrl('https://test.foo?code=%s');

        $deliveryEntity->setShippingMethod($shippingMethod);
        $trackingInfoStruct = $this->factory->createFromDelivery($deliveryEntity);

        $this->assertNotNull($trackingInfoStruct);
        $this->assertSame($expectedCode, $trackingInfoStruct->getCode());
        $this->assertSame($expectedUrl, $trackingInfoStruct->getUrl());
        $this->assertSame($expectedCarrier, $trackingInfoStruct->getCarrier());
    }

    public function testOnlyOneCodeAccepted(): void
    {

        $deliveryEntity = new OrderDeliveryEntity();
        $deliveryEntity->setUniqueIdentifier('testDelivery');
        $deliveryEntity->setTrackingCodes([
            '1234',
            'test'
        ]);

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setName('Test carrier');
        $shippingMethod->setUniqueIdentifier('testShippingMethod');
        $shippingMethod->setTrackingUrl('https://test.foo?code=%s');

        $deliveryEntity->setShippingMethod($shippingMethod);
        $trackingInfoStruct = $this->factory->createFromDelivery($deliveryEntity);
        $this->assertNull($trackingInfoStruct);
    }

    public function testInfoStructCreatedByArguments(): void
    {
        $expectedCode = '1234';
        $expectedCarrier = 'Test carrier';
        $trackingInfoStruct = $this->factory->create($expectedCarrier, $expectedCode, 'https://test.foo?code=%s');
        $expectedUrl = 'https://test.foo?code=1234';

        $this->assertNotNull($trackingInfoStruct);
        $this->assertSame($expectedCode, $trackingInfoStruct->getCode());
        $this->assertSame($expectedUrl, $trackingInfoStruct->getUrl());
        $this->assertSame($expectedCarrier, $trackingInfoStruct->getCarrier());

    }

    public function testExceptionIsThrownWithoutPlaceholderInUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $trackingInfoStruct = $this->factory->create('Test', 'testCode', 'https://test.foo?code');

        $this->assertNull($trackingInfoStruct);
    }

    /**
     * @dataProvider invalidCodes
     * @param string $url
     * @param string $trackingCode
     * @return void
     */
    public function testInvalidTrackingCodeCharacter(string $trackingCode): void
    {

        $trackingInfoStruct = $this->factory->create('test', $trackingCode, 'https://foo.bar/%s');
        $expected = '';
        $this->assertSame($expected, $trackingInfoStruct->getUrl());

    }

    public function invalidCodes(): array
    {
        return [
            ['some{code'],
            ['some}code'],
            ['some<code'],
            ['some>code'],
            ['some#code'],
            [str_repeat('1', 200)],
        ];
    }
}