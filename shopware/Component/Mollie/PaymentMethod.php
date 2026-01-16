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
}
