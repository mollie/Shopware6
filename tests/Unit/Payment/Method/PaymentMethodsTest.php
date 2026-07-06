<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Method;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Method\AlmaPayment;
use Mollie\Shopware\Component\Payment\Method\ApplePayPayment;
use Mollie\Shopware\Component\Payment\Method\BancomatPayPayment;
use Mollie\Shopware\Component\Payment\Method\BanContactPayment;
use Mollie\Shopware\Component\Payment\Method\BankTransferPayment;
use Mollie\Shopware\Component\Payment\Method\BelfiusPayment;
use Mollie\Shopware\Component\Payment\Method\BilliePayment;
use Mollie\Shopware\Component\Payment\Method\BizumPayment;
use Mollie\Shopware\Component\Payment\Method\BlikPayment;
use Mollie\Shopware\Component\Payment\Method\CardPayment;
use Mollie\Shopware\Component\Payment\Method\DirectDebitPayment;
use Mollie\Shopware\Component\Payment\Method\EpsPayment;
use Mollie\Shopware\Component\Payment\Method\GiftCardPayment;
use Mollie\Shopware\Component\Payment\Method\IdealPayment;
use Mollie\Shopware\Component\Payment\Method\In3Payment;
use Mollie\Shopware\Component\Payment\Method\KbcPayment;
use Mollie\Shopware\Component\Payment\Method\KlarnaPayment;
use Mollie\Shopware\Component\Payment\Method\MbWayPayment;
use Mollie\Shopware\Component\Payment\Method\MobilePayPayment;
use Mollie\Shopware\Component\Payment\Method\MultiBancoPayment;
use Mollie\Shopware\Component\Payment\Method\MyBankPayment;
use Mollie\Shopware\Component\Payment\Method\PayByBankPayment;
use Mollie\Shopware\Component\Payment\Method\PayconiqPayment;
use Mollie\Shopware\Component\Payment\Method\PayPalExpressPayment;
use Mollie\Shopware\Component\Payment\Method\PayPalPayment;
use Mollie\Shopware\Component\Payment\Method\PaySafeCardPayment;
use Mollie\Shopware\Component\Payment\Method\PosPayment;
use Mollie\Shopware\Component\Payment\Method\Przelewy24Payment;
use Mollie\Shopware\Component\Payment\Method\RivertyPayment;
use Mollie\Shopware\Component\Payment\Method\SatisPayPayment;
use Mollie\Shopware\Component\Payment\Method\SwishPayment;
use Mollie\Shopware\Component\Payment\Method\TrustlyPayment;
use Mollie\Shopware\Component\Payment\Method\TwintPayment;
use Mollie\Shopware\Component\Payment\Method\VippsPayment;
use Mollie\Shopware\Component\Payment\Method\VoucherPayment;
use Mollie\Shopware\Component\Payment\Method\WeroPayment;
use Mollie\Shopware\Unit\Payment\Fake\FakeFinalize;
use Mollie\Shopware\Unit\Payment\Fake\FakePay;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

#[CoversClass(AlmaPayment::class)]
#[CoversClass(ApplePayPayment::class)]
#[CoversClass(BanContactPayment::class)]
#[CoversClass(BancomatPayPayment::class)]
#[CoversClass(BankTransferPayment::class)]
#[CoversClass(BelfiusPayment::class)]
#[CoversClass(BilliePayment::class)]
#[CoversClass(BizumPayment::class)]
#[CoversClass(BlikPayment::class)]
#[CoversClass(CardPayment::class)]
#[CoversClass(DirectDebitPayment::class)]
#[CoversClass(EpsPayment::class)]
#[CoversClass(GiftCardPayment::class)]
#[CoversClass(IdealPayment::class)]
#[CoversClass(In3Payment::class)]
#[CoversClass(KbcPayment::class)]
#[CoversClass(KlarnaPayment::class)]
#[CoversClass(MbWayPayment::class)]
#[CoversClass(MobilePayPayment::class)]
#[CoversClass(MultiBancoPayment::class)]
#[CoversClass(MyBankPayment::class)]
#[CoversClass(PayByBankPayment::class)]
#[CoversClass(PayconiqPayment::class)]
#[CoversClass(PayPalExpressPayment::class)]
#[CoversClass(PayPalPayment::class)]
#[CoversClass(PaySafeCardPayment::class)]
#[CoversClass(PosPayment::class)]
#[CoversClass(Przelewy24Payment::class)]
#[CoversClass(RivertyPayment::class)]
#[CoversClass(SatisPayPayment::class)]
#[CoversClass(SwishPayment::class)]
#[CoversClass(TrustlyPayment::class)]
#[CoversClass(TwintPayment::class)]
#[CoversClass(VippsPayment::class)]
#[CoversClass(VoucherPayment::class)]
#[CoversClass(WeroPayment::class)]
final class PaymentMethodsTest extends TestCase
{
    #[DataProvider('providePaymentMethods')]
    public function testPaymentMethodReturnsCorrectEnumAndName(
        string $handlerClass,
        PaymentMethod $expectedMethod,
        string $expectedName,
    ): void {
        $handler = new $handlerClass(new FakePay(), new FakeFinalize(), new NullLogger());

        $this->assertSame($expectedMethod, $handler->getPaymentMethod());
        $this->assertSame($expectedName, $handler->getName());
    }

    public static function providePaymentMethods(): array
    {
        return [
            'alma' => [AlmaPayment::class, PaymentMethod::ALMA, 'Alma'],
            'applepay' => [ApplePayPayment::class, PaymentMethod::APPLEPAY, 'Apple Pay'],
            'bancontact' => [BanContactPayment::class, PaymentMethod::BAN_CONTACT, 'Bancontact'],
            'bancomatpay' => [BancomatPayPayment::class, PaymentMethod::BANCOMAT_PAY, 'Bancomat Pay'],
            'banktransfer' => [BankTransferPayment::class, PaymentMethod::BANK_TRANSFER, 'Banktransfer'],
            'belfius' => [BelfiusPayment::class, PaymentMethod::BELFIUS, 'Belfius'],
            'billie' => [BilliePayment::class, PaymentMethod::BILLIE, 'Billie'],
            'bizum' => [BizumPayment::class, PaymentMethod::BIZUM, 'Bizum'],
            'blik' => [BlikPayment::class, PaymentMethod::BLIK, 'Blik'],
            'card' => [CardPayment::class, PaymentMethod::CREDIT_CARD, 'Card'],
            'directdebit' => [DirectDebitPayment::class, PaymentMethod::DIRECT_DEBIT, 'SEPA Direct Debit'],
            'eps' => [EpsPayment::class, PaymentMethod::EPS, 'eps'],
            'giftcard' => [GiftCardPayment::class, PaymentMethod::GIFT_CARD, 'Gift cards'],
            'ideal' => [IdealPayment::class, PaymentMethod::IDEAL, 'iDEAL | Wero'],
            'in3' => [In3Payment::class, PaymentMethod::IN3, 'iDeal IN3'],
            'kbc' => [KbcPayment::class, PaymentMethod::KBC, 'KBC/CBC Payment'],
            'klarna' => [KlarnaPayment::class, PaymentMethod::KLARNA, 'Klarna'],
            'mbway' => [MbWayPayment::class, PaymentMethod::MB_WAY, 'MB Way'],
            'mobilepay' => [MobilePayPayment::class, PaymentMethod::MOBILE_PAY, 'MobilePay'],
            'multibanco' => [MultiBancoPayment::class, PaymentMethod::MULTI_BANCO, 'Multibanco'],
            'mybank' => [MyBankPayment::class, PaymentMethod::MY_BANK, 'MyBank'],
            'paybybank' => [PayByBankPayment::class, PaymentMethod::PAY_BY_BANK, 'Pay by Bank'],
            'payconiq' => [PayconiqPayment::class, PaymentMethod::PAYCONIQ, 'Payconiq'],
            'paypalexpress' => [PayPalExpressPayment::class, PaymentMethod::PAYPAL, 'PayPal Express'],
            'paypal' => [PayPalPayment::class, PaymentMethod::PAYPAL, 'PayPal'],
            'paysafecard' => [PaySafeCardPayment::class, PaymentMethod::PAY_SAFE_CARD, 'paysafecard'],
            'pos' => [PosPayment::class, PaymentMethod::POS, 'POS Terminal'],
            'przelewy24' => [Przelewy24Payment::class, PaymentMethod::PRZELEWY24, 'Przelewy24'],
            'riverty' => [RivertyPayment::class, PaymentMethod::RIVERTY, 'Riverty'],
            'satispay' => [SatisPayPayment::class, PaymentMethod::SATISPAY, 'Satispay'],
            'swish' => [SwishPayment::class, PaymentMethod::SWISH, 'Swish'],
            'trustly' => [TrustlyPayment::class, PaymentMethod::TRUSTLY, 'Trustly'],
            'twint' => [TwintPayment::class, PaymentMethod::TWINT, 'TWINT'],
            'vipps' => [VippsPayment::class, PaymentMethod::VIPPS, 'Vipps'],
            'voucher' => [VoucherPayment::class, PaymentMethod::VOUCHER, 'Voucher'],
            'wero' => [WeroPayment::class, PaymentMethod::WERO, 'Wero'],
        ];
    }

    public function testApplePaySetsPaymentTokenWhenPresent(): void
    {
        $handler = new ApplePayPayment(new FakePay(), new FakeFinalize(), new NullLogger());
        $payment = $this->createPayment();
        $dataBag = new RequestDataBag(['paymentToken' => 'apple-token-123']);
        $customer = new CustomerEntity();

        $result = $handler->applyPaymentSpecificParameters($payment, $dataBag, $customer);

        $this->assertSame($payment, $result);
        $this->assertSame('apple-token-123', $payment->getApplePayPaymentToken());
    }

    public function testApplePaySkipsTokenWhenNotPresent(): void
    {
        $handler = new ApplePayPayment(new FakePay(), new FakeFinalize(), new NullLogger());
        $payment = $this->createPayment();
        $dataBag = new RequestDataBag([]);
        $customer = new CustomerEntity();

        $result = $handler->applyPaymentSpecificParameters($payment, $dataBag, $customer);

        $this->assertSame($payment, $result);
        $this->assertNull($payment->getApplePayPaymentToken());
    }

    public function testBancomatPaySetsBillingPhoneFromDataBag(): void
    {
        $handler = new BancomatPayPayment(new FakePay(), new FakeFinalize(), new NullLogger());
        $payment = $this->createPaymentWithBillingAddress();
        $dataBag = new RequestDataBag(['molliePayPhone' => '+49123456789']);
        $customer = new CustomerEntity();

        $handler->applyPaymentSpecificParameters($payment, $dataBag, $customer);

        $this->assertSame('+49123456789', $payment->getBillingAddress()->getPhone());
    }

    public function testBancomatPayFallsBackToBillingAddressPhone(): void
    {
        $handler = new BancomatPayPayment(new FakePay(), new FakeFinalize(), new NullLogger());
        $payment = $this->createPaymentWithBillingAddress('+49000000000');
        $dataBag = new RequestDataBag([]);
        $customer = new CustomerEntity();

        $handler->applyPaymentSpecificParameters($payment, $dataBag, $customer);

        $this->assertSame('+49000000000', $payment->getBillingAddress()->getPhone());
    }

    public function testBizumSetsBillingPhoneFromDataBag(): void
    {
        $handler = new BizumPayment(new FakePay(), new FakeFinalize(), new NullLogger());
        $payment = $this->createPaymentWithBillingAddress();
        $dataBag = new RequestDataBag(['molliePayPhone' => '+34600000000']);
        $customer = new CustomerEntity();

        $handler->applyPaymentSpecificParameters($payment, $dataBag, $customer);

        $this->assertSame('+34600000000', $payment->getBillingAddress()->getPhone());
    }

    public function testCardPaymentReturnsEarlyWhenMandateIdIsSet(): void
    {
        $handler = new CardPayment(new FakePay(), new FakeFinalize(), new NullLogger());
        $payment = $this->createPayment();
        $payment->setMandateId('mandate-123');
        $dataBag = new RequestDataBag(['creditCardToken' => 'token-should-be-ignored']);
        $customer = new CustomerEntity();

        $result = $handler->applyPaymentSpecificParameters($payment, $dataBag, $customer);

        $this->assertSame($payment, $result);
        $this->assertNull($payment->getCardToken());
    }

    public function testCardPaymentSetsCardTokenFromDataBag(): void
    {
        $handler = new CardPayment(new FakePay(), new FakeFinalize(), new NullLogger());
        $payment = $this->createPayment();
        $dataBag = new RequestDataBag(['creditCardToken' => 'card-token-abc']);
        $customer = new CustomerEntity();

        $result = $handler->applyPaymentSpecificParameters($payment, $dataBag, $customer);

        $this->assertSame($payment, $result);
        $this->assertSame('card-token-abc', $payment->getCardToken());
        $this->assertFalse($payment->isStoreCredentials());
    }

    public function testCardPaymentStoresCredentialsWhenSavePaymentDetailsIsSet(): void
    {
        $handler = new CardPayment(new FakePay(), new FakeFinalize(), new NullLogger());
        $payment = $this->createPayment();
        $dataBag = new RequestDataBag(['creditCardToken' => 'card-token-abc', 'savePaymentDetails' => true]);
        $customer = new CustomerEntity();

        $handler->applyPaymentSpecificParameters($payment, $dataBag, $customer);

        $this->assertTrue($payment->isStoreCredentials());
    }

    public function testPayPalExpressSetsAuthenticationIdFromDataBag(): void
    {
        $handler = new PayPalExpressPayment(new FakePay(), new FakeFinalize(), new NullLogger());
        $payment = $this->createPayment();
        $dataBag = new RequestDataBag(['authenticationId' => 'auth-id-xyz']);
        $customer = new CustomerEntity();

        $handler->applyPaymentSpecificParameters($payment, $dataBag, $customer);

        $this->assertSame('auth-id-xyz', $payment->getAuthenticationId());
    }

    public function testPayPalExpressHasExpressSuffixInTechnicalName(): void
    {
        $handler = new PayPalExpressPayment(new FakePay(), new FakeFinalize(), new NullLogger());

        $this->assertStringEndsWith('express', $handler->getTechnicalName());
    }

    public function testPaySafeCardSetsCustomerReferenceFromCustomerNumber(): void
    {
        $handler = new PaySafeCardPayment(new FakePay(), new FakeFinalize(), new NullLogger());
        $payment = $this->createPayment();
        $dataBag = new RequestDataBag([]);
        $customer = new CustomerEntity();
        $customer->setCustomerNumber('CUST-001');

        $handler->applyPaymentSpecificParameters($payment, $dataBag, $customer);

        $this->assertSame('CUST-001', $payment->getCustomerReference());
    }

    public function testPosSetsTerminalIdWhenPresent(): void
    {
        $handler = new PosPayment(new FakePay(), new FakeFinalize(), new NullLogger());
        $payment = $this->createPayment();
        $dataBag = new RequestDataBag(['terminalId' => 'terminal-abc']);
        $customer = new CustomerEntity();

        $handler->applyPaymentSpecificParameters($payment, $dataBag, $customer);

        $this->assertSame('terminal-abc', $payment->getTerminalId());
    }

    public function testPosSkipsTerminalIdWhenNotPresent(): void
    {
        $handler = new PosPayment(new FakePay(), new FakeFinalize(), new NullLogger());
        $payment = $this->createPayment();
        $dataBag = new RequestDataBag([]);
        $customer = new CustomerEntity();

        $handler->applyPaymentSpecificParameters($payment, $dataBag, $customer);

        $this->assertNull($payment->getTerminalId());
    }

    private function createPayment(): CreatePayment
    {
        return new CreatePayment('Order #1', 'https://example.com/return', new Money(10.00, 'EUR'));
    }

    private function createPaymentWithBillingAddress(string $phone = ''): CreatePayment
    {
        $payment = $this->createPayment();
        $address = new Address('test@example.com', '', 'John', 'Doe', 'Main St 1', '12345', 'Berlin', 'DE');
        if ($phone !== '') {
            $address->setPhone($phone);
        }
        $payment->setBillingAddress($address);

        return $payment;
    }
}
