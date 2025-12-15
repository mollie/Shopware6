<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Action\Finalize;
use Mollie\Shopware\Component\Payment\Action\Pay;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\BankTransferAwareInterface;
use Psr\Log\LoggerInterface;

final class FakeBankTransferAwarePaymentHandler extends AbstractMolliePaymentHandler implements BankTransferAwareInterface
{
    public function __construct(
        Pay $pay,
        Finalize $finalize,
        LoggerInterface $logger,
    ) {
        parent::__construct($pay, $finalize, $logger);
    }

    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::BANK_TRANSFER;
    }

    public function getName(): string
    {
        return 'Fake Bank Transfer Handler';
    }
}
