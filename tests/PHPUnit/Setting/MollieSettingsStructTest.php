<?php declare(strict_types=1);

namespace MolliePayments\Tests\Setting;

use DateTime;
use DateTimeZone;
use Kiener\MolliePayments\Handler\Method\BankTransferPayment;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use PHPUnit\Framework\TestCase;

final class MollieSettingsStructTest extends TestCase
{
    /**
     * test to get a maximum range of order life time days
     *
     * @dataProvider orderLifeTimeDaysData
     * @param $lifeTimeDays
     * @param int|null $realLifeTimeDays
     */
    public function testGetOrderLifetimeDays($lifeTimeDays, ?int $realLifeTimeDays)
    {
        $settingsStruct = new MollieSettingStruct();

        $settingsStruct->assign([
            'orderLifetimeDays' => $lifeTimeDays
        ]);
        $actualValue = $settingsStruct->getOrderLifetimeDays();
        $this->assertSame($realLifeTimeDays, $actualValue);

    }

    /**
     * test get the correct calculation based on oderLifetimeDays config
     *
     * @dataProvider orderLifeTimeDaysData
     * @param $lifeTimeDays
     * @param int|null $realLifeTimeDays
     * @param string|null $expectedDateString
     * @throws \Exception
     */
    public function testGetOrderLifetimeDate($lifeTimeDays, ?int $realLifeTimeDays, ?string $expectedDateString)
    {
        $settingsStruct = new MollieSettingStruct();

        $settingsStruct->assign([
            'orderLifetimeDays' => $lifeTimeDays
        ]);
        $actualValue = $settingsStruct->getOrderLifetimeDate();
        $this->assertSame($expectedDateString, $actualValue);
    }

    /**
     * test get the correct calculation based on paymentMethodBankTransferDueDateDays config
     *
     * @dataProvider orderBankTransferDueDays
     * @param $lifeTimeDays
     * @param int|null $realLifeTimeDays
     * @param string|null $expectedDateString
     */
    public function testBankTransferDueDays($lifeTimeDays, ?int $realLifeTimeDays, ?string $expectedDateString)
    {
        $settingsStruct = new MollieSettingStruct();

        $settingsStruct->assign([
            'paymentMethodBankTransferDueDateDays' => $lifeTimeDays
        ]);
        $actualValue = $settingsStruct->getPaymentMethodBankTransferDueDateDays();
        $this->assertSame($realLifeTimeDays, $actualValue);
    }

    /**
     * test to get correct value range based on paymentMethodBankTransferDueDateDays config
     *
     * @dataProvider orderBankTransferDueDays
     * @param $lifeTimeDays
     * @param int|null $realLifeTimeDays
     * @param string|null $expectedDateString
     * @throws \Exception
     */
    public function testBankTransferDueDate($lifeTimeDays, ?int $realLifeTimeDays, ?string $expectedDateString)
    {
        $settingsStruct = new MollieSettingStruct();

        $settingsStruct->assign([
            'orderLifetimeDays' => $lifeTimeDays,
            'paymentMethodBankTransferDueDateDays' => $realLifeTimeDays
        ]);
        $actualValue = $settingsStruct->getPaymentMethodBankTransferDueDate();
        $this->assertSame($expectedDateString, $actualValue);
    }
    
    public function orderLifeTimeDaysData()
    {
        $today = (new DateTime())->setTimezone(new DateTimeZone('UTC'));

        return [
            'oderLifeTime cannot be smaller than minimum' => [-1, MollieSettingStruct::ORDER_EXPIRES_AT_MIN_DAYS, (clone $today)->modify('+1 day')->format('Y-m-d')],
            'orderLifeTime cannot be bigger than maximum' => [1000, MollieSettingStruct::ORDER_EXPIRES_AT_MAX_DAYS, (clone $today)->modify('+100 day')->format('Y-m-d')],
            'orderLifeTime can be set' => [10, 10, (clone $today)->modify('+10 day')->format('Y-m-d')],
            'orderLifeTime can be null' => [null, null, null],
            'orderLifeTime can be empty' => ['', null, null],
            'orderLifeTime is zero, should be handled with null' => ['', null, null],
        ];
    }

    public function orderBankTransferDueDays()
    {
        $today = (new DateTime())->setTimezone(new DateTimeZone('UTC'));

        return [
            'dueDateDays cannot be smaller than minimum' => [-1, BankTransferPayment::DUE_DATE_MIN_DAYS, (clone $today)->modify('+1 day')->format('Y-m-d')],
            'dueDateDays cannot be bigger than maximum' => [1000, BankTransferPayment::DUE_DATE_MAX_DAYS, (clone $today)->modify('+100 day')->format('Y-m-d')],
            'dueDateDays can be set' => [10, 10, (clone $today)->modify('+10 day')->format('Y-m-d')],
            'dueDateDays can be null' => [null, null, null],
            'dueDateDays can be empty' => ['', null, null],
            'dueDateDays is zero, should be handled with null' => ['', null, null],
        ];
    }
}
