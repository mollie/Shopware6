<?php declare(strict_types=1);


namespace Kiener\MolliePayments\Tests\Setting;

use DateTime;
use DateTimeZone;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use PHPUnit\Framework\TestCase;


final class MollieSettingsStructTest extends TestCase
{


    /**
     * test to get a maximum range of order life time days
     *
     * @author Vitalij Mik
     * @dataProvider orderLifeTimeDaysData
     * @param $lifeTimeDays
     * @param int|null $realLifeTimeDays
     */
    public function testGetOrderLifetimeDays($lifeTimeDays,?int $realLifeTimeDays)
    {
        $settingsStruct = new MollieSettingStruct();

        $settingsStruct->assign([
           'orderLifetimeDays'=>$lifeTimeDays
        ]);
        $actualValue =$settingsStruct->getOrderLifetimeDays();
        $this->assertSame($realLifeTimeDays,$actualValue);

    }

    /**
     * test get the correct calculation based on oderLifetimeDays config
     *
     * @author Vitalij Mik
     * @dataProvider orderLifeTimeDaysData
     * @param $lifeTimeDays
     * @param int|null $realLifeTimeDays
     * @param string|null $expectedDateString
     * @throws \Exception
     */
    public function testGetOrderLifetimeDate($lifeTimeDays,?int $realLifeTimeDays,?string $expectedDateString)
    {
        $settingsStruct = new MollieSettingStruct();

        $settingsStruct->assign([
            'orderLifetimeDays'=>$lifeTimeDays
        ]);
        $actualValue =$settingsStruct->getOrderLifetimeDate();
        $this->assertSame($expectedDateString,$actualValue);
    }


    public function orderLifeTimeDaysData()
    {
        $today = (new DateTime())->setTimezone(new DateTimeZone('UTC'));

        return [
            'oderLifeTime cannot be smaller than minimum' => [-1,1,(clone $today)->modify('+1 day')->format('Y-m-d')],
            'orderLifeTime cannot be bigger than maximum' => [1000,100,(clone $today)->modify('+100 day')->format('Y-m-d')],
            'orderLifeTime can be set' => [10,10,(clone $today)->modify('+10 day')->format('Y-m-d')],
            'orderLifeTime can be null' => [null,null,null],
            'orderLifeTime can be empty' => ['',null,null],
        ];
    }

}
