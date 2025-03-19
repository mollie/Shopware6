<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Service;

use Kiener\MolliePayments\Components\ShipmentManager\Exceptions\NoDeliveriesFoundExceptions;
use Kiener\MolliePayments\Service\TrackingInfoStructFactory;
use Kiener\MolliePayments\Service\UrlParsingService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
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
        $this->factory = new TrackingInfoStructFactory(new UrlParsingService(), new NullLogger());
    }

    /**
     * @throws \Kiener\MolliePayments\Components\ShipmentManager\Exceptions\NoDeliveriesFoundException
     */
    public function testTrackingFromOrder(): void
    {
        $expectedCode = '1234';
        $expectedCarrier = 'Test carrier';
        $expectedUrl = 'https://test.foo?code=1234';

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setName($expectedCarrier);
        $shippingMethod->setUniqueIdentifier('testShippingMethod');
        $shippingMethod->setTrackingUrl('https://test.foo?code=%s');

        $deliveryEntity = new OrderDeliveryEntity();
        $deliveryEntity->setUniqueIdentifier('testDelivery');
        $deliveryEntity->setShippingMethod($shippingMethod);
        $deliveryEntity->setTrackingCodes([$expectedCode]);

        $order = new OrderEntity();
        $order->setDeliveries(new OrderDeliveryCollection([$deliveryEntity]));

        $trackingInfoStruct = $this->factory->trackingFromOrder($order);

        $this->assertNotNull($trackingInfoStruct);
        $this->assertSame($expectedCode, $trackingInfoStruct->getCode());
        $this->assertSame($expectedUrl, $trackingInfoStruct->getUrl());
        $this->assertSame($expectedCarrier, $trackingInfoStruct->getCarrier());
    }

    /**
     * @throws NoDeliveriesFoundExceptions
     */
    public function testOnlyOneCodeAccepted(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setName('Test carrier');
        $shippingMethod->setUniqueIdentifier('testShippingMethod');
        $shippingMethod->setTrackingUrl('https://test.foo?code=%s');

        $deliveryEntity = new OrderDeliveryEntity();
        $deliveryEntity->setUniqueIdentifier('testDelivery');
        $deliveryEntity->setShippingMethod($shippingMethod);
        $deliveryEntity->setTrackingCodes([
            '1234',
            'test',
        ]);

        $order = new OrderEntity();
        $order->setDeliveries(new OrderDeliveryCollection([$deliveryEntity]));

        $trackingInfoStruct = $this->factory->trackingFromOrder($order);

        $this->assertNull($trackingInfoStruct);
    }

    public function testInfoStructCreatedByArguments(): void
    {
        $trackingInfoStruct = $this->factory->create(
            'Test carrier',
            '1234',
            'https://test.foo?code=%s'
        );

        $this->assertNotNull($trackingInfoStruct);

        $this->assertSame('1234', $trackingInfoStruct->getCode());
        $this->assertSame('https://test.foo?code=1234', $trackingInfoStruct->getUrl());
        $this->assertSame('Test carrier', $trackingInfoStruct->getCarrier());
    }

    public function testUrlWithCodeIsInvalid(): void
    {
        $expectedCode = '/123 4%foo=bar?test';
        $expectedCarrier = 'Test carrier';
        $trackingInfoStruct = $this->factory->create($expectedCarrier, $expectedCode, 'https://test.foo?code=%s');
        $expectedUrl = '';

        $this->assertNotNull($trackingInfoStruct);
        $this->assertSame($expectedCode, $trackingInfoStruct->getCode());
        $this->assertSame($expectedUrl, $trackingInfoStruct->getUrl());
        $this->assertSame($expectedCarrier, $trackingInfoStruct->getCarrier());
    }

    public function testInfoStructWithCommaSeparator(): void
    {
        $expectedCode = '1234';
        $givenCode = $expectedCode . ',' . str_repeat('-', 100);
        $expectedCarrier = 'Test carrier';
        $trackingInfoStruct = $this->factory->create($expectedCarrier, $givenCode, 'https://test.foo?code=%s');
        $expectedUrl = 'https://test.foo?code=1234';

        $this->assertNotNull($trackingInfoStruct);
        $this->assertSame($expectedCode, $trackingInfoStruct->getCode());
        $this->assertSame($expectedUrl, $trackingInfoStruct->getUrl());
        $this->assertSame($expectedCarrier, $trackingInfoStruct->getCarrier());
    }

    public function testInfoStructWithSemicolonSeparator(): void
    {
        $expectedCode = '1234';
        $givenCode = $expectedCode . ';' . str_repeat('-', 100);
        $expectedCarrier = 'Test carrier';
        $trackingInfoStruct = $this->factory->create($expectedCarrier, $givenCode, 'https://test.foo?code=%s');
        $expectedUrl = 'https://test.foo?code=1234';

        $this->assertNotNull($trackingInfoStruct);
        $this->assertSame($expectedCode, $trackingInfoStruct->getCode());
        $this->assertSame($expectedUrl, $trackingInfoStruct->getUrl());
        $this->assertSame($expectedCarrier, $trackingInfoStruct->getCarrier());
    }

    public function testCommaSeparatorHasHigherPriority(): void
    {
        $expectedCode = '1234';
        $givenCode = $expectedCode . ',5678;' . str_repeat('-', 100);
        $expectedCarrier = 'Test carrier';
        $trackingInfoStruct = $this->factory->create($expectedCarrier, $givenCode, 'https://test.foo?code=%s');
        $expectedUrl = 'https://test.foo?code=1234';

        $this->assertNotNull($trackingInfoStruct);
        $this->assertSame($expectedCode, $trackingInfoStruct->getCode());
        $this->assertSame($expectedUrl, $trackingInfoStruct->getUrl());
        $this->assertSame($expectedCarrier, $trackingInfoStruct->getCarrier());
    }

    /**
     * @dataProvider invalidShippingUrlPatterns
     */
    public function testUrlEmptyOnInvalidShippingURLs(string $invalidPattern): void
    {
        $trackingInfoStruct = $this->factory->create('test', 'valid-code', 'https://foo.bar/' . $invalidPattern);

        $this->assertSame('', $trackingInfoStruct->getUrl());
    }

    public function invalidShippingUrlPatterns(): array
    {
        return [
            ['%s%'],
        ];
    }
}
