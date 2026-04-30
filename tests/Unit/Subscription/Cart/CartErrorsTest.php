<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Cart;

use Mollie\Shopware\Component\Subscription\Cart\Error\InvalidGuestAccountError;
use Mollie\Shopware\Component\Subscription\Cart\Error\InvalidPaymentMethodError;
use Mollie\Shopware\Component\Subscription\Cart\Error\PaymentMethodAvailabilityNotice;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\Error;

#[CoversClass(InvalidGuestAccountError::class)]
#[CoversClass(InvalidPaymentMethodError::class)]
#[CoversClass(PaymentMethodAvailabilityNotice::class)]
final class CartErrorsTest extends TestCase
{
    /**
     * @param array<string,string> $expectedParameters
     */
    #[DataProvider('errorProvider')]
    public function testErrorExposesExpectedFields(
        \Closure $factory,
        string $expectedId,
        string $expectedMessageKey,
        int $expectedLevel,
        bool $expectedBlocksOrder,
        array $expectedParameters
    ): void {
        /** @var Error $error */
        $error = $factory();

        $this->assertSame($expectedId, $error->getId());
        $this->assertSame($expectedMessageKey, $error->getMessageKey());
        $this->assertSame($expectedLevel, $error->getLevel());
        $this->assertSame($expectedBlocksOrder, $error->blockOrder());
        $this->assertSame($expectedParameters, $error->getParameters());
    }

    /**
     * @return array<string,array{0:\Closure,1:string,2:string,3:int,4:bool,5:array<string,string>}>
     */
    public static function errorProvider(): array
    {
        return [
            'invalid-guest-account' => [
                static fn (): InvalidGuestAccountError => new InvalidGuestAccountError(),
                'mollie-payments-cart-guest-account',
                'mollie-payments-cart-guest-account',
                Error::LEVEL_ERROR,
                true,
                [],
            ],
            'invalid-payment-method' => [
                static fn (): InvalidPaymentMethodError => new InvalidPaymentMethodError(),
                'mollie-payments-cart-error-method-invalid',
                'mollie-payments-cart-error-method-invalid',
                Error::LEVEL_ERROR,
                true,
                [],
            ],
            'payment-method-availability-notice' => [
                static fn (): PaymentMethodAvailabilityNotice => new PaymentMethodAvailabilityNotice('line-item-1'),
                'line-item-1',
                'mollie-payments-cart-error-paymentmethod-availability',
                Error::LEVEL_NOTICE,
                false,
                ['lineItemId' => 'line-item-1'],
            ],
        ];
    }
}
