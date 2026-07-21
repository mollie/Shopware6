<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

enum PaymentMethod: string
{
    case ALMA = 'alma';
    case APPLEPAY = 'applepay';
    case BACS = 'bacs';
    case BANCOMAT_PAY = 'bancomatpay';
    case BAN_CONTACT = 'bancontact';
    case BANK_TRANSFER = 'banktransfer';
    case BELFIUS = 'belfius';
    case BILLIE = 'billie';
    case BILLINK = 'billink';
    case BIZUM = 'bizum';
    case BLIK = 'blik';
    case CREDIT_CARD = 'creditcard';
    case DIRECT_DEBIT = 'directdebit';
    case EPS = 'eps';
    case GIFT_CARD = 'giftcard';
    case IDEAL = 'ideal';
    case IN3 = 'in3';
    case KBC = 'kbc';
    case KLARNA = 'klarna';
    case MB_WAY = 'mbway';
    case MOBILE_PAY = 'mobilepay';
    case MULTI_BANCO = 'multibanco';
    case MY_BANK = 'mybank';
    case PAY_BY_BANK = 'paybybank';
    case PAYPAL = 'paypal';
    case PAY_SAFE_CARD = 'paysafecard';

    case PAYCONIQ = 'payconiq';

    case POS = 'pointofsale';
    case PRZELEWY24 = 'przelewy24';
    case RIVERTY = 'riverty';
    case SATISPAY = 'satispay';
    case SWISH = 'swish';
    case TRUSTLY = 'trustly';
    case TWINT = 'twint';

    case VIPPS = 'vipps';
    case VOUCHER = 'voucher';
    case WERO = 'wero';

    /**
     * Whether Mollie accepts this method in a payment link's "allowedMethods". Payment links support
     * fewer methods than the Payments API, and sending an unsupported one makes Mollie reject the
     * whole request, so unknown/newer methods default to not supported until explicitly listed here.
     */
    public function isSupportedForPaymentLink(): bool
    {
        return match ($this) {
            self::APPLEPAY,
            self::BACS,
            self::BANCOMAT_PAY,
            self::BAN_CONTACT,
            self::BANK_TRANSFER,
            self::BELFIUS,
            self::BILLIE,
            self::BLIK,
            self::CREDIT_CARD,
            self::EPS,
            self::GIFT_CARD,
            self::IDEAL,
            self::IN3,
            self::KBC,
            self::KLARNA,
            self::MB_WAY,
            self::MULTI_BANCO,
            self::MY_BANK,
            self::PAY_BY_BANK,
            self::PAYPAL,
            self::PAY_SAFE_CARD,
            self::POS,
            self::PRZELEWY24,
            self::RIVERTY,
            self::SATISPAY,
            self::SWISH,
            self::TRUSTLY,
            self::TWINT,
            self::VOUCHER => true,
            default => false,
        };
    }

    /**
     * UNTDID 4461 payment means type code for e-invoices (ZUGFeRD/XRechnung).
     */
    public function eInvoicePaymentMeansCode(): int
    {
        return match ($this) {
            self::CREDIT_CARD => 48,
            self::BANK_TRANSFER => 58,
            self::DIRECT_DEBIT, self::BACS => 59,
            default => 68,
        };
    }
}
