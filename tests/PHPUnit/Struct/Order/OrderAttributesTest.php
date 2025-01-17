<?php


namespace MolliePayments\Tests\Struct\Order;

use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderEntity;

class OrderAttributesTest extends TestCase
{
    /**
     * @return void
     */
    public function testIsSubscription()
    {
        $order = new OrderEntity();

        $attributes = new OrderAttributes($order);

        $this->assertEquals(false, $attributes->isTypeSubscription());
    }

    /**
     * @return void
     */
    public function testIsSubscriptionWithMollieId()
    {
        $order = new OrderEntity();
        $order->setCustomFields([
            'mollie_payments' => [
                'mollieSubscriptionId' => 'sub_xyz',
            ]
        ]);

        $attributes = new OrderAttributes($order);

        $this->assertEquals(true, $attributes->isTypeSubscription());
    }

    /**
     * @return void
     */
    public function testIsSubscriptionWithShopwareId()
    {
        $order = new OrderEntity();
        $order->setCustomFields([
            'mollie_payments' => [
                'swSubscriptionId' => '1231244142',
            ]
        ]);

        $attributes = new OrderAttributes($order);

        $this->assertEquals(true, $attributes->isTypeSubscription());
    }

    public function testReadBankDataFromCustomFields()
    {
        $expectedBankName = 'Stichting Mollie Payments';
        $expectedBankBIC = 'TESTNL10';
        $expectedBankAccount = 'NL10TEST000100100';
        $order = new OrderEntity();
        $order->setCustomFields([
            'mollie_payments' => [
                'bankName' => $expectedBankName,
                'bankBic' => $expectedBankBIC,
                'bankAccount' => $expectedBankAccount,
            ]
        ]);

        $attributes = new OrderAttributes($order);

        $this->assertSame($expectedBankName, $attributes->getBankName());
        $this->assertSame($expectedBankBIC, $attributes->getBankBic());
        $this->assertSame($expectedBankAccount, $attributes->getBankAccount());
    }

    public function testBankTransferDetailsAreSetFromApiStruct()
    {
        $expectedBankName = 'Stichting Mollie Payments';
        $expectedBankBIC = 'TESTNL10';
        $expectedBankAccount = 'NL10TEST000100100';

        $bankTransferDetails = new \stdClass();
        $bankTransferDetails->bankName = $expectedBankName;
        $bankTransferDetails->bankAccount = $expectedBankAccount;
        $bankTransferDetails->bankBic = $expectedBankBIC;

        $order = new OrderEntity();

        $attributes = new OrderAttributes($order);
        $attributes->setBankTransferDetails($bankTransferDetails);

        $this->assertSame($expectedBankName, $attributes->getBankName());
        $this->assertSame($expectedBankBIC, $attributes->getBankBic());
        $this->assertSame($expectedBankAccount, $attributes->getBankAccount());
    }
}
