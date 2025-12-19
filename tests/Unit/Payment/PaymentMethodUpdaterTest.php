<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\PaymentMethodUpdater;
use Mollie\Shopware\Entity\PaymentMethod\PaymentMethod as PaymentMethodExtension;
use Mollie\Shopware\Unit\Payment\Fake\FakeOrderTransactionRepository;
use Mollie\Shopware\Unit\Payment\Fake\FakePaymentMethodRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;

#[CoversClass(PaymentMethodUpdater::class)]
final class PaymentMethodUpdaterTest extends TestCase
{
    public function testPaymentMethodsAreSame(): void
    {
        $context = new Context(new SystemSource());

        $paymentMethodUpdater = new PaymentMethodUpdater(
            new FakeOrderTransactionRepository(),
            new FakePaymentMethodRepository(),
            new NullLogger()
        );
        $paymentMethodExtension = new PaymentMethodExtension('shopwareId', PaymentMethod::CREDIT_CARD);
        $newPaymentMethodId = $paymentMethodUpdater->updatePaymentMethod($paymentMethodExtension, PaymentMethod::CREDIT_CARD, 'test', 'test', 'test', $context);

        $this->assertSame('shopwareId', $newPaymentMethodId);
    }

    public function testApplePayAndCreditCardDoesNotChange(): void
    {
        $context = new Context(new SystemSource());

        $paymentMethodUpdater = new PaymentMethodUpdater(
            new FakeOrderTransactionRepository(),
            new FakePaymentMethodRepository(),
            new NullLogger()
        );
        $paymentMethodExtension = new PaymentMethodExtension('shopwareId', PaymentMethod::APPLEPAY);
        $newPaymentMethodId = $paymentMethodUpdater->updatePaymentMethod($paymentMethodExtension, PaymentMethod::CREDIT_CARD, 'test', 'test', 'test', $context);

        $this->assertSame('shopwareId', $newPaymentMethodId);
    }
}
