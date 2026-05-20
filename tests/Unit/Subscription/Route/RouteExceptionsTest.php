<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Route;

use Mollie\Shopware\Component\Subscription\Route\RenewException;
use Mollie\Shopware\Component\Subscription\Route\SubscriptionException;
use Mollie\Shopware\Component\Subscription\Route\WebhookException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(RenewException::class)]
#[CoversClass(SubscriptionException::class)]
#[CoversClass(WebhookException::class)]
final class RouteExceptionsTest extends TestCase
{
    /**
     * @param list<string> $expectedMessageFragments
     */
    #[DataProvider('exceptionFactoryProvider')]
    public function testExceptionFactoryProducesExpectedShape(
        \Closure $factory,
        int $expectedStatusCode,
        string $expectedErrorCode,
        array $expectedMessageFragments
    ): void {
        /** @var HttpException $exception */
        $exception = $factory();

        $this->assertSame($expectedStatusCode, $exception->getStatusCode());
        $this->assertSame($expectedErrorCode, $exception->getErrorCode());
        foreach ($expectedMessageFragments as $fragment) {
            $this->assertStringContainsString($fragment, $exception->getMessage());
        }
    }

    /**
     * @return array<string,array{0:\Closure,1:int,2:string,3:list<string>}>
     */
    public static function exceptionFactoryProvider(): array
    {
        return [
            'subscription-not-found' => [
                static fn (): SubscriptionException => SubscriptionException::subscriptionNotFound('sub-123'),
                Response::HTTP_BAD_REQUEST,
                SubscriptionException::SUBSCRIPTION_NOT_FOUND,
                ['sub-123'],
            ],
            'webhook-payment-id-not-provided' => [
                static fn (): WebhookException => WebhookException::paymentIdNotProvided('sub-123'),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                WebhookException::SUBSCRIPTION_WITHOUT_PAYMENT_ID,
                ['sub-123'],
            ],
            'renew-subscription-without-order' => [
                static fn (): RenewException => RenewException::subscriptionWithoutOrder('sub-123'),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                RenewException::SUBSCRIPTION_WITHOUT_ORDER,
                ['sub-123'],
            ],
            'renew-subscriptions-disabled' => [
                static fn (): RenewException => RenewException::subscriptionsDisabled('sub-123', 'channel-456'),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                RenewException::SUBSCRIPTIONS_DISABLED,
                ['sub-123', 'channel-456'],
            ],
            'renew-invalid-payment-id' => [
                static fn (): RenewException => RenewException::invalidPaymentId('sub-123', 'pay-789'),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                RenewException::INVALID_PAYMENT_ID,
                ['sub-123', 'pay-789'],
            ],
            'renew-order-without-transaction' => [
                static fn (): RenewException => RenewException::orderWithoutTransaction('sub-123', '10000'),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                RenewException::INVALID_PAYMENT_ID,
                ['sub-123', '10000'],
            ],
            'renew-subscription-without-address' => [
                static fn (): RenewException => RenewException::subscriptionWithoutAddress('sub-123'),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                RenewException::SUBSCRIPTION_WITHOUT_ADDRESS,
                ['sub-123'],
            ],
            'renew-order-without-deliveries' => [
                static fn (): RenewException => RenewException::orderWithoutDeliveries('sub-123', '10000'),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                RenewException::ORDER_WITHOUT_DELIVERIES,
                ['sub-123', '10000'],
            ],
            'renew-subscription-without-customer' => [
                static fn (): RenewException => RenewException::subscriptionWithoutCustomer('sub-123'),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                RenewException::SUBSCRIPTION_WITHOUT_CUSTOMER,
                ['sub-123'],
            ],
        ];
    }
}
