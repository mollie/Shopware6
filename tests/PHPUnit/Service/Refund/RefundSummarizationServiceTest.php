<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Service\Refund;


use Kiener\MolliePayments\Service\Refund\RefundSummarizationService;
use PHPUnit\Framework\TestCase;

class RefundSummarizationServiceTest extends TestCase
{
    /**
     * @var RefundSummarizationService
     */
    private $service;

    protected function setUp(): void
    {
        $this->service = new RefundSummarizationService();
    }

    /**
     * @dataProvider summarizeDataProvider
     */
    public function testCanSummarize(float ...$values): void
    {
        $expected = 0;
        $dataSet = [];

        foreach ($values as $value) {
            $dataSet[] = ['amount' => $value];
            $expected += $value;
        }

        $actual = $this->service->getLineItemsRefundSum($dataSet);

        static::assertEquals($expected, $actual);
    }

    public static function summarizeDataProvider(): array
    {
        return [
            'single value' => [10.0],
            'multiple values' => [10.0, 20.0, 30.0],
        ];
    }
}