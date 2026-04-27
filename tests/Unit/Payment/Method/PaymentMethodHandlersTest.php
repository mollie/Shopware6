<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Method;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Method\AlmaPayment;
use Mollie\Shopware\Component\Payment\Method\ApplePayPayment;
use Mollie\Shopware\Component\Payment\Method\BanContactPayment;
use Mollie\Shopware\Component\Payment\Method\BancomatPayPayment;
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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

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
final class PaymentMethodHandlersTest extends TestCase
{
    /**
     * Creates a handler instance without invoking the constructor (which requires
     * Pay, Finalize, LoggerInterface). The tested methods are pure and do not use
     * any constructor-injected services.
     *
     * @template T of AbstractMolliePaymentHandler
     * @param class-string<T> $class
     * @return T
     */
    private static function make(string $class): AbstractMolliePaymentHandler
    {
        return (new \ReflectionClass($class))->newInstanceWithoutConstructor();
    }

    /**
     * @return array<string, array{AbstractMolliePaymentHandler, PaymentMethod, string, string}>
     */
    public static function provideHandlers(): array
    {
        return [
            AlmaPayment::class => [self::make(AlmaPayment::class), PaymentMethod::ALMA, 'Alma', 'payment_mollie_alma'],
            ApplePayPayment::class => [self::make(ApplePayPayment::class), PaymentMethod::APPLEPAY, 'Apple Pay', 'payment_mollie_applepay'],
            BanContactPayment::class => [self::make(BanContactPayment::class), PaymentMethod::BAN_CONTACT, 'Bancontact', 'payment_mollie_bancontact'],
            BancomatPayPayment::class => [self::make(BancomatPayPayment::class), PaymentMethod::BANCOMAT_PAY, 'Bancomat Pay', 'payment_mollie_bancomatpay'],
            BankTransferPayment::class => [self::make(BankTransferPayment::class), PaymentMethod::BANK_TRANSFER, 'Banktransfer', 'payment_mollie_banktransfer'],
            BelfiusPayment::class => [self::make(BelfiusPayment::class), PaymentMethod::BELFIUS, 'Belfius', 'payment_mollie_belfius'],
            BilliePayment::class => [self::make(BilliePayment::class), PaymentMethod::BILLIE, 'Billie', 'payment_mollie_billie'],
            BizumPayment::class => [self::make(BizumPayment::class), PaymentMethod::BIZUM, 'Bizum', 'payment_mollie_bizum'],
            BlikPayment::class => [self::make(BlikPayment::class), PaymentMethod::BLIK, 'Blik', 'payment_mollie_blik'],
            CardPayment::class => [self::make(CardPayment::class), PaymentMethod::CREDIT_CARD, 'Card', 'payment_mollie_creditcard'],
            DirectDebitPayment::class => [self::make(DirectDebitPayment::class), PaymentMethod::DIRECT_DEBIT, 'SEPA Direct Debit', 'payment_mollie_directdebit'],
            EpsPayment::class => [self::make(EpsPayment::class), PaymentMethod::EPS, 'eps', 'payment_mollie_eps'],
            GiftCardPayment::class => [self::make(GiftCardPayment::class), PaymentMethod::GIFT_CARD, 'Gift cards', 'payment_mollie_giftcard'],
            IdealPayment::class => [self::make(IdealPayment::class), PaymentMethod::IDEAL, 'iDEAL | Wero', 'payment_mollie_ideal'],
            In3Payment::class => [self::make(In3Payment::class), PaymentMethod::IN3, 'iDeal IN3', 'payment_mollie_in3'],
            KbcPayment::class => [self::make(KbcPayment::class), PaymentMethod::KBC, 'KBC/CBC Payment', 'payment_mollie_kbc'],
            KlarnaPayment::class => [self::make(KlarnaPayment::class), PaymentMethod::KLARNA, 'Klarna', 'payment_mollie_klarna'],
            MbWayPayment::class => [self::make(MbWayPayment::class), PaymentMethod::MB_WAY, 'MB Way', 'payment_mollie_mbway'],
            MobilePayPayment::class => [self::make(MobilePayPayment::class), PaymentMethod::MOBILE_PAY, 'MobilePay', 'payment_mollie_mobilepay'],
            MultiBancoPayment::class => [self::make(MultiBancoPayment::class), PaymentMethod::MULTI_BANCO, 'Multibanco', 'payment_mollie_multibanco'],
            MyBankPayment::class => [self::make(MyBankPayment::class), PaymentMethod::MY_BANK, 'MyBank', 'payment_mollie_mybank'],
            PayByBankPayment::class => [self::make(PayByBankPayment::class), PaymentMethod::PAY_BY_BANK, 'Pay by Bank', 'payment_mollie_paybybank'],
            PayconiqPayment::class => [self::make(PayconiqPayment::class), PaymentMethod::PAYCONIQ, 'Payconiq', 'payment_mollie_payconiq'],
            PayPalExpressPayment::class => [self::make(PayPalExpressPayment::class), PaymentMethod::PAYPAL, 'PayPal Express', 'payment_mollie_paypalexpress'],
            PayPalPayment::class => [self::make(PayPalPayment::class), PaymentMethod::PAYPAL, 'PayPal', 'payment_mollie_paypal'],
            PaySafeCardPayment::class => [self::make(PaySafeCardPayment::class), PaymentMethod::PAY_SAFE_CARD, 'paysafecard', 'payment_mollie_paysafecard'],
            PosPayment::class => [self::make(PosPayment::class), PaymentMethod::POS, 'POS Terminal', 'payment_mollie_pointofsale'],
            Przelewy24Payment::class => [self::make(Przelewy24Payment::class), PaymentMethod::PRZELEWY24, 'Przelewy24', 'payment_mollie_przelewy24'],
            RivertyPayment::class => [self::make(RivertyPayment::class), PaymentMethod::RIVERTY, 'Riverty', 'payment_mollie_riverty'],
            SatisPayPayment::class => [self::make(SatisPayPayment::class), PaymentMethod::SATISPAY, 'Satispay', 'payment_mollie_satispay'],
            SwishPayment::class => [self::make(SwishPayment::class), PaymentMethod::SWISH, 'Swish', 'payment_mollie_swish'],
            TrustlyPayment::class => [self::make(TrustlyPayment::class), PaymentMethod::TRUSTLY, 'Trustly', 'payment_mollie_trustly'],
            TwintPayment::class => [self::make(TwintPayment::class), PaymentMethod::TWINT, 'TWINT', 'payment_mollie_twint'],
            VippsPayment::class => [self::make(VippsPayment::class), PaymentMethod::VIPPS, 'Vipps', 'payment_mollie_vipps'],
            VoucherPayment::class => [self::make(VoucherPayment::class), PaymentMethod::VOUCHER, 'Voucher', 'payment_mollie_voucher'],
        ];
    }

    #[DataProvider('provideHandlers')]
    public function testGetPaymentMethod(AbstractMolliePaymentHandler $handler, PaymentMethod $expectedMethod): void
    {
        $this->assertSame($expectedMethod, $handler->getPaymentMethod());
    }

    #[DataProvider('provideHandlers')]
    public function testGetName(AbstractMolliePaymentHandler $handler, PaymentMethod $expectedMethod, string $expectedName): void
    {
        $this->assertSame($expectedName, $handler->getName());
    }

    #[DataProvider('provideHandlers')]
    public function testGetTechnicalName(AbstractMolliePaymentHandler $handler, PaymentMethod $expectedMethod, string $expectedName, string $expectedTechnicalName): void
    {
        $this->assertSame($expectedTechnicalName, $handler->getTechnicalName());
    }

    #[DataProvider('provideHandlers')]
    public function testGetIconFileName(AbstractMolliePaymentHandler $handler, PaymentMethod $expectedMethod): void
    {
        $this->assertSame($expectedMethod->value . '-icon', $handler->getIconFileName());
    }
}
