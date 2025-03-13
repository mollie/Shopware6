<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Tests\Struct\MollieApi;

use Kiener\MolliePayments\Struct\MollieApi\ShipmentTrackingInfoStruct;
use PHPUnit\Framework\TestCase;

class ShipmentTrackingInfoStructTest extends TestCase
{
    /**
     * @var string
     */
    private $carrier = 'Mollie';

    /**
     * @var string
     */
    private $code = '1234567890';

    /**
     * @var string
     */
    private $url = 'https://mollie.com';

    public function testStructSetsValuesCorrectly()
    {
        $struct = new ShipmentTrackingInfoStruct($this->carrier, $this->code, $this->url);

        $this->assertEquals($this->carrier, $struct->getCarrier());
        $this->assertEquals($this->code, $struct->getCode());
        $this->assertEquals($this->url, $struct->getUrl());
    }

    public function testStructConvertsToArray()
    {
        $struct = new ShipmentTrackingInfoStruct($this->carrier, $this->code, $this->url);

        $this->assertIsArray($struct->toArray());
        $this->assertSame([
            'carrier' => $this->carrier,
            'code' => $this->code,
            'url' => $this->url,
        ], $struct->toArray());
    }
}
