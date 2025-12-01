<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Data;

use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Kiener\MolliePayments\Handler\Method\BancomatPayment;
use Kiener\MolliePayments\Handler\Method\BankTransferPayment;
use Kiener\MolliePayments\Handler\Method\VoucherPayment;
use Mollie\Shopware\Component\Payment\PaymentMethodRepository;
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
        ApplePayPayment::PAYMENT_METHOD_NAME => ApplePayPayment::class,
        BankTransferPayment::PAYMENT_METHOD_NAME => BankTransferPayment::class,
        VoucherPayment::PAYMENT_METHOD_NAME => VoucherPayment::class,
        BancomatPayment::PAYMENT_METHOD_NAME => BancomatPayment::class,
    ];

    public function getPaymentMethodByTechnicalName(string $technicalName, Context $context): PaymentMethodEntity
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('payment_method.repository');
        $criteria = new Criteria();

        /** @var PaymentMethodRepository $molliePaymentMethods */
        $molliePaymentMethods = $this->getContainer()->get(PaymentMethodRepository::class);
        $handler = $molliePaymentMethods->findByPaymentMethod($technicalName);
        if ($handler === null) {
            $handler = $this->paymentMethodMapping[$technicalName];
            if ($handler === null) {
                throw new \RuntimeException(sprintf('Handler not found for technical name "%s"', $technicalName));
            }
        }
        if (! is_string($handler)) {
            $handler = get_class($handler);
        }

        $criteria->addFilter(new EqualsFilter('handlerIdentifier', $handler));
        $searchResult = $repository->search($criteria, $context);
        $firstPaymentMethod = $searchResult->first();
        if ($firstPaymentMethod === null) {
            throw new \RuntimeException(sprintf('Payment method not found for technical name "%s"', $technicalName));
        }

        return $firstPaymentMethod;
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
