<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Tests\Service;

use Kiener\MolliePayments\Service\MolliePaymentExtractor;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Uuid\Uuid;

class MolliePaymentExtractorTest extends TestCase
{
    public const MOLLIE_PAYMENT_METHOD = MolliePaymentExtractor::MOLLIE_PAYMENT_HANDLER_NAMESPACE . '\FooMethod';

    public function testExtractWithNullCollection(): void
    {
        $extractor = new MolliePaymentExtractor();
        self::assertNull($extractor->extractLast(null));
    }

    public function testExtractWithEmptyCollection(): void
    {
        $extractor = new MolliePaymentExtractor();
        self::assertNull($extractor->extractLast(new OrderTransactionCollection([])));
    }

    public function testExtractWithSingleMollieTransactionCollection(): void
    {
        $transaction = $this->createTransaction(new \DateTime(), self::MOLLIE_PAYMENT_METHOD);
        $collection = new OrderTransactionCollection([$transaction]);
        $extractor = new MolliePaymentExtractor();
        self::assertSame($transaction, $extractor->extractLast($collection));
    }

    public function testExtractWithOtherTransactionCollection(): void
    {
        $transaction = $this->createTransaction(new \DateTime(), 'FooMethod');
        $collection = new OrderTransactionCollection([$transaction]);
        $extractor = new MolliePaymentExtractor();
        self::assertNull($extractor->extractLast($collection));
    }

    public function testExtractWithMultiTransactionCollection(): void
    {
        $twoDaysAgo = (new \DateTime())->modify('-2 days');
        $yesterday = (new \DateTime())->modify('-1 day');
        $threeDaysAgo = (new \DateTime())->modify('-3 days');
        $transactionThree = $this->createTransaction($threeDaysAgo, 'foo');
        $transactionTwo = $this->createTransaction($twoDaysAgo, self::MOLLIE_PAYMENT_METHOD);
        $transactionOne = $this->createTransaction($yesterday, self::MOLLIE_PAYMENT_METHOD);
        $collection = new OrderTransactionCollection([$transactionTwo, $transactionOne, $transactionThree]);
        $extractor = new MolliePaymentExtractor();

        self::assertSame($transactionOne, $extractor->extractLast($collection));
    }

    public function testExtractWithMultiLastNotMollieTransactionCollection(): void
    {
        $twoDaysAgo = (new \DateTime())->modify('-2 days');
        $yesterday = (new \DateTime())->modify('-1 day');
        $threeDaysAgo = (new \DateTime())->modify('-3 days');
        $transactionThree = $this->createTransaction($threeDaysAgo, self::MOLLIE_PAYMENT_METHOD);
        $transactionTwo = $this->createTransaction($twoDaysAgo, self::MOLLIE_PAYMENT_METHOD);
        $transactionOne = $this->createTransaction($yesterday, 'foo');
        $collection = new OrderTransactionCollection([$transactionTwo, $transactionOne, $transactionThree]);
        $extractor = new MolliePaymentExtractor();
        self::assertNull($extractor->extractLast($collection));
    }

    private function createTransaction(\DateTime $createdAt, ?string $paymentMethodName): OrderTransactionEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId(Uuid::randomHex());
        $transaction->setCreatedAt($createdAt);
        if (!is_null($paymentMethodName)) {
            $payment = $this->createPaymentMethod($paymentMethodName);
            $transaction->setPaymentMethod($payment);
        }
        return $transaction;
    }

    private function createPaymentMethod(string $methodName): PaymentMethodEntity
    {
        $payment = new PaymentMethodEntity();
        $payment->setId(Uuid::randomHex());
        $payment->setHandlerIdentifier($methodName);
        return $payment;
    }
}
