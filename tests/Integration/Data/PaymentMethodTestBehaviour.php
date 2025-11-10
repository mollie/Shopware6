<?php
declare(strict_types=1);

namespace Mollie\Integration\Data;

use Kiener\MolliePayments\Handler\Method\AlmaPayment;
use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Kiener\MolliePayments\Handler\Method\BancomatPayment;
use Kiener\MolliePayments\Handler\Method\BanContactPayment;
use Kiener\MolliePayments\Handler\Method\BankTransferPayment;
use Kiener\MolliePayments\Handler\Method\BelfiusPayment;
use Kiener\MolliePayments\Handler\Method\BilliePayment;
use Kiener\MolliePayments\Handler\Method\BizumPayment;
use Kiener\MolliePayments\Handler\Method\BlikPayment;
use Kiener\MolliePayments\Handler\Method\CreditCardPayment;
use Kiener\MolliePayments\Handler\Method\DirectDebitPayment;
use Kiener\MolliePayments\Handler\Method\EpsPayment;
use Kiener\MolliePayments\Handler\Method\GiftCardPayment;
use Kiener\MolliePayments\Handler\Method\GiroPayPayment;
use Kiener\MolliePayments\Handler\Method\iDealPayment;
use Kiener\MolliePayments\Handler\Method\KbcPayment;
use Kiener\MolliePayments\Handler\Method\KlarnaOnePayment;
use Kiener\MolliePayments\Handler\Method\MbWayPayment;
use Kiener\MolliePayments\Handler\Method\MultibancoPayment;
use Kiener\MolliePayments\Handler\Method\MyBankPayment;
use Kiener\MolliePayments\Handler\Method\PayByBankPayment;
use Kiener\MolliePayments\Handler\Method\PayconiqPayment;
use Kiener\MolliePayments\Handler\Method\PayPalPayment;
use Kiener\MolliePayments\Handler\Method\Przelewy24Payment;
use Kiener\MolliePayments\Handler\Method\RivertyPayment;
use Kiener\MolliePayments\Handler\Method\SatispayPayment;
use Kiener\MolliePayments\Handler\Method\SwishPayment;
use Kiener\MolliePayments\Handler\Method\TrustlyPayment;
use Kiener\MolliePayments\Handler\Method\TwintPayment;
use Kiener\MolliePayments\Handler\Method\VoucherPayment;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

trait PaymentMethodTestBehaviour
{
    use IntegrationTestBehaviour;

    /**
     * We need this mapping array because technicalName is not defined in shopware 6.4
     *
     * @var array|\class-string[]
     */
    private array $paymentMethodMapping = [
        AlmaPayment::PAYMENT_METHOD_NAME => AlmaPayment::class,
        ApplePayPayment::PAYMENT_METHOD_NAME => ApplePayPayment::class,
        BancomatPayment::PAYMENT_METHOD_NAME => BancomatPayment::class,
        BanContactPayment::PAYMENT_METHOD_NAME => BanContactPayment::class,
        BankTransferPayment::PAYMENT_METHOD_NAME => BankTransferPayment::class,
        BelfiusPayment::PAYMENT_METHOD_NAME => BelfiusPayment::class,
        BilliePayment::PAYMENT_METHOD_NAME => BilliePayment::class,
        BizumPayment::PAYMENT_METHOD_NAME => BizumPayment::class,
        BlikPayment::PAYMENT_METHOD_NAME => BlikPayment::class,
        CreditCardPayment::PAYMENT_METHOD_NAME => CreditCardPayment::class,
        DirectDebitPayment::PAYMENT_METHOD_NAME => DirectDebitPayment::class,
        EpsPayment::PAYMENT_METHOD_NAME => EpsPayment::class,
        GiftCardPayment::PAYMENT_METHOD_NAME => GiftCardPayment::class,
        GiroPayPayment::PAYMENT_METHOD_NAME => GiroPayPayment::class,
        iDealPayment::PAYMENT_METHOD_NAME => iDealPayment::class,
        KbcPayment::PAYMENT_METHOD_NAME => KbcPayment::class,
        KlarnaOnePayment::PAYMENT_METHOD_NAME => KlarnaOnePayment::class,
        MbWayPayment::PAYMENT_METHOD_NAME => MbWayPayment::class,
        MultibancoPayment::PAYMENT_METHOD_NAME => MultibancoPayment::class,
        MyBankPayment::PAYMENT_METHOD_NAME => MyBankPayment::class,
        PayByBankPayment::PAYMENT_METHOD_NAME => PayByBankPayment::class,
        PayconiqPayment::PAYMENT_METHOD_NAME => PayconiqPayment::class,
        PayPalPayment::PAYMENT_METHOD_NAME => PayPalPayment::class,
        Przelewy24Payment::PAYMENT_METHOD_NAME => Przelewy24Payment::class,
        RivertyPayment::PAYMENT_METHOD_NAME => RivertyPayment::class,
        SatispayPayment::PAYMENT_METHOD_NAME => SatispayPayment::class,
        SwishPayment::PAYMENT_METHOD_NAME => SwishPayment::class,
        TrustlyPayment::PAYMENT_METHOD_NAME => TrustlyPayment::class,
        TwintPayment::PAYMENT_METHOD_NAME => TwintPayment::class,
        VoucherPayment::PAYMENT_METHOD_NAME => VoucherPayment::class,
    ];

    public function getPaymentMethodByTechnicalName(string $technicalName, Context $context): PaymentMethodEntity
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('payment_method.repository');
        $criteria = new Criteria();

        $handlerIdentifier = $this->paymentMethodMapping[$technicalName] ?? null;
        if ($handlerIdentifier === null) {
            throw new \RuntimeException('Handler identifier not found');
        }
        $criteria->addFilter(new EqualsFilter('handlerIdentifier', $handlerIdentifier));

        return $repository->search($criteria, $context)->first();
    }

    public function getPaymentMethodByIdentifier(string $handlerIdentifier, Context $context): PaymentMethodEntity
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('payment_method.repository');
        $criteria = new Criteria();

        $criteria->addFilter(new EqualsFilter('handlerIdentifier', $handlerIdentifier));

        return $repository->search($criteria, $context)->first();
    }

    public function activatePaymentMethod(PaymentMethodEntity $paymentMethod, Context $context): void
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('payment_method.repository');

        $data = [
            'id' => $paymentMethod->getId(),
            'active' => true,
        ];
        $repository->upsert([$data], $context);
    }

    private function assignPaymentMethodToSalesChannel(PaymentMethodEntity $paymentMethod, SalesChannelEntity $salesChannel, Context $context): void
    {
        $repository = $this->getContainer()->get('sales_channel_payment_method.repository');
        $data = [
            'salesChannelId' => $salesChannel->getId(),
            'paymentMethodId' => $paymentMethod->getId(),
        ];
        $repository->upsert([$data], $context);
    }
}
