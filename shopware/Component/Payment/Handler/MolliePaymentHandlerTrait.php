<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Handler;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Action\Finalize;
use Mollie\Shopware\Component\Payment\Action\FinalizeInterface;
use Mollie\Shopware\Component\Payment\Action\Pay;
use Mollie\Shopware\Component\Payment\Action\PayInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

trait MolliePaymentHandlerTrait
{
    protected LoggerInterface $logger;
    private PayInterface $pay;
    private FinalizeInterface $finalize;

    public function __construct(
        #[Autowire(service: Pay::class)]
        PayInterface $pay,
        #[Autowire(service: Finalize::class)]
        FinalizeInterface $finalize,
        #[Autowire(service: 'monolog.logger.mollie')]
        LoggerInterface $logger,
    ) {
        $this->pay = $pay;
        $this->finalize = $finalize;
        $this->logger = $logger;
    }

    abstract public function getPaymentMethod(): PaymentMethod;

    abstract public function getName(): string;

    public function applyPaymentSpecificParameters(CreatePayment $payment, RequestDataBag $dataBag, CustomerEntity $customer): CreatePayment
    {
        return $payment;
    }

    public function getIconFileName(): string
    {
        return $this->getPaymentMethod()->value . '-icon';
    }

    public function getTechnicalName(): string
    {
        return 'payment_mollie_' . $this->getPaymentMethod()->value;
    }
}
