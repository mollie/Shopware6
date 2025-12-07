<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Handler;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Action\Finalize;
use Mollie\Shopware\Component\Payment\Action\Pay;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

#[AutoconfigureTag('shopware.payment.method')]
#[AutoconfigureTag('shopware.payment.method.async')]
#[AutoconfigureTag('mollie.payment.method')]
abstract class AbstractMolliePaymentHandler extends AbstractPaymentHandler
{
    private const TECHNICAL_NAME_PREFIX = 'payment_mollie_';

    public function __construct(
        #[Autowire(service: Pay::class)]
        private Pay $pay,
        #[Autowire(service: Finalize::class)]
        private Finalize $finalize,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public function pay(Request $request, PaymentTransactionStruct $transaction, Context $context, ?Struct $validateStruct): RedirectResponse
    {
        try {
            $dataBag = new RequestDataBag($request->request->all());

            return $this->pay->execute($this, $transaction, $dataBag, $context);
        } catch (\Throwable $exception) {
            $this->logger->error('Mollie Pay Process Failed', [
                'error' => $exception->getMessage(),
                'paymentMethod' => $this->getPaymentMethod()->value
            ]);
            throw PaymentException::asyncProcessInterrupted($transaction->getOrderTransactionId(), $exception->getMessage(), $exception);
        }
    }

    public function finalize(Request $request, PaymentTransactionStruct $transaction, Context $context): void
    {
        try {
            $this->finalize->execute($transaction, $context);
        } catch (HttpException $exception) {
            $this->logger->error('Payment is aborted or failed', [
                'error' => $exception->getMessage(),
                'paymentMethod' => $this->getPaymentMethod()->value
            ]);
            throw $exception;
        } catch (\Throwable $exception) {
            $this->logger->error('Payment failed unexpected', [
                'error' => $exception->getMessage(),
                'paymentMethod' => $this->getPaymentMethod()->value
            ]);
            throw PaymentException::asyncFinalizeInterrupted($transaction->getOrderTransactionId(), $exception->getMessage(), $exception);
        }
    }

    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        return false;
    }

    public function applyPaymentSpecificParameters(CreatePayment $payment,RequestDataBag $dataBag, CustomerEntity $customer): CreatePayment
    {
        return $payment;
    }

    public function getIconFileName(): string
    {
        return $this->getPaymentMethod()->value . '-icon';
    }

    abstract public function getPaymentMethod(): PaymentMethod;

    abstract public function getName(): string;

    public function getTechnicalName(): string
    {
        return self::TECHNICAL_NAME_PREFIX . $this->getPaymentMethod()->value;
    }
}
