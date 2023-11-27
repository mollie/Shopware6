<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Service;

use Kiener\MolliePayments\Service\TrackingInfoStructFactory;
use PHPUnit\Framework\TestCase;

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

    /**
     * @dataProvider invalidCodes
     * @param string $url
     * @param string $trackingCode
     * @return void
     */
    public function testInvalidTrackingCodeCharacter(string $trackingCode):void{

        $trackingInfoStruct = $this->factory->create('test',$trackingCode,'https://foo.bar');
        $expected ='';
        $this->assertSame($expected,$trackingInfoStruct->getUrl());

    }
    public function invalidCodes():array{
        return [
            ['some{code'],
            ['some}code'],
            ['some<code'],
            ['some>code'],
            ['some#code'],
        ];
    }
}