<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\CreateOrder;
use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\CreatePaymentLink;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\SequenceType;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\PayloadBuilderInterface;
use Mollie\Shopware\Component\Transaction\TransactionDataStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

final class FakePayloadBuilder implements PayloadBuilderInterface
{
    /** @var list<array{allowedMethods: string[], handler: null|AbstractMolliePaymentHandler}> */
    private array $paymentLinkCalls = [];

    public function buildPayment(TransactionDataStruct $transactionData, AbstractMolliePaymentHandler $paymentHandler, RequestDataBag $dataBag, Context $context): CreatePayment
    {
        throw new \RuntimeException('Not used by the payment link flow.');
    }

    public function buildOrder(TransactionDataStruct $transactionData, AbstractMolliePaymentHandler $paymentHandler, RequestDataBag $dataBag, Context $context): CreateOrder
    {
        throw new \RuntimeException('Not used by the payment link flow.');
    }

    public function buildPaymentLink(TransactionDataStruct $transactionData, array $allowedMethods, ?AbstractMolliePaymentHandler $paymentHandler, Context $context): CreatePaymentLink
    {
        $this->paymentLinkCalls[] = [
            'allowedMethods' => $allowedMethods,
            'handler' => $paymentHandler,
        ];

        $address = new Address('customer@shop.test', 'Mr', 'Max', 'Mustermann', 'Teststreet 1', '12345', 'Testcity', 'DE');

        $createPaymentLink = new CreatePaymentLink(
            'Order 10000',
            'https://shop.test/mollie/payment/transaction-1',
            new Money(25.0, 'EUR'),
            new LineItemCollection(),
            $address,
            $address,
            SequenceType::ONEOFF
        );
        $createPaymentLink->setAllowedMethods($allowedMethods);

        return $createPaymentLink;
    }

    /**
     * @return list<array{allowedMethods: string[], handler: null|AbstractMolliePaymentHandler}>
     */
    public function getPaymentLinkCalls(): array
    {
        return $this->paymentLinkCalls;
    }

    /**
     * @return array{allowedMethods: string[], handler: null|AbstractMolliePaymentHandler}
     */
    public function getLastPaymentLinkCall(): array
    {
        $last = end($this->paymentLinkCalls);

        if ($last === false) {
            throw new \RuntimeException('The payload builder has not built a payment link.');
        }

        return $last;
    }
}
