<?php declare(strict_types=1);

namespace MolliePayments\Tests\Service\MollieApi\Builder;

use Kiener\MolliePayments\Handler\Method\PayPalPayment;
use MolliePayments\Tests\Fakes\FakeContainer;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Uuid\Uuid;


class MollieOrderBuilderTest extends AbstractMollieOrderBuilder
{


    /**
     * @return \string[][]
     */
    public function getFormatValues()
    {
        return
            [
                'no_custom_format' => ['10000', ''],
                'just_ordernumber' => ['10000', '{ordernumber}'],
                'custom_prefix' => ['R10000', 'R{ordernumber}'],
                'custom_suffix' => ['10000-stage', '{ordernumber}-stage'],
                'full_format' => ['R10000-stage', 'R{ordernumber}-stage'],
            ];
    }

    /**
     * This test verifies that we can set a custom format for our order numbers.
     * Mollie should then have this being set in the order number
     * of the payload for our request.
     *
     * @dataProvider getFormatValues
     * @param string $expected
     * @param string $format
     * @return void
     * @throws \Exception
     */
    public function testOrderNumberFormat(string $expected, string $format): void
    {
        # set a custom format for
        # our current settings that are used in our fake/mock
        $this->settingStruct->setFormatOrderNumber($format);

        $order = new OrderEntity();
        $order->setAmountTotal(14);
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setTaxStatus('ok');
        $order->setOrderNumber('10000');

        $data = $this->builder->build(
            $order,
            '123',
            'paypal',
            $this->salesChannelContext,
            new PayPalPayment($this->loggerService, new FakeContainer()),
            []
        );

        self::assertSame($expected, $data['orderNumber']);
    }

}
