<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

use Mollie\Shopware\Component\Payment\MethodRemover\AbstractPaymentRemover;
use Mollie\Shopware\Component\Transaction\TransactionDataStruct;
use Mollie\Shopware\Entity\PaymentMethod\PaymentMethod as PaymentMethodExtension;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class PaymentLinkMethodResolver implements PaymentLinkMethodResolverInterface
{
    /**
     * The removers are injected as a lazy tagged iterator on purpose: they depend on the
     * PaymentHandlerLocator (and therefore on the whole payment handler graph, which leads back to
     * the PayloadBuilder). Resolving them eagerly in the constructor would create a circular
     * dependency, so they are only instantiated when the iterator is traversed at runtime.
     *
     * @param iterable<AbstractPaymentRemover> $paymentMethodRemovers
     */
    public function __construct(
        #[Autowire(service: PaymentMethodRepository::class)]
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly OrderConverter $orderConverter,
        #[AutowireIterator('mollie.method.remover')]
        private readonly iterable $paymentMethodRemovers,
    ) {
    }

    public function resolve(TransactionDataStruct $transactionData, Context $context): array
    {
        // When the order was placed with a mollie payment method the link is restricted to exactly
        // that method - the payment method was already decided.
        $paymentMethod = $transactionData->getTransaction()->getPaymentMethod();
        if ($paymentMethod !== null) {
            $molliePaymentMethod = $this->molliePaymentMethodValue($paymentMethod);
            if ($molliePaymentMethod !== null) {
                return [$molliePaymentMethod];
            }
        }

        // No mollie method chosen -> reuse the same removers as the checkout to determine which
        // methods are available for this order (availability, voucher, business customer and
        // subscription rules).
        $order = $transactionData->getOrder();
        $orderContext = $this->orderConverter->assembleSalesChannelContext($order, $context);
        $paymentMethods = $this->paymentMethodRepository->findActiveMollieMethods($order->getSalesChannelId(), $context);

        foreach ($this->paymentMethodRemovers as $paymentMethodRemover) {
            $paymentMethods = $paymentMethodRemover->remove($paymentMethods, $order->getId(), $orderContext);
        }

        return $this->toMollieMethodValues($paymentMethods);
    }

    /**
     * @param PaymentMethodCollection<PaymentMethodEntity> $paymentMethods
     *
     * @return string[]
     */
    private function toMollieMethodValues(PaymentMethodCollection $paymentMethods): array
    {
        $values = [];
        foreach ($paymentMethods as $paymentMethod) {
            $molliePaymentMethod = $this->molliePaymentMethodValue($paymentMethod);
            if ($molliePaymentMethod === null) {
                continue;
            }
            $values[] = $molliePaymentMethod;
        }

        return array_values(array_unique($values));
    }

    private function molliePaymentMethodValue(PaymentMethodEntity $paymentMethod): ?string
    {
        $extension = $paymentMethod->getExtension(Mollie::EXTENSION);
        if (! $extension instanceof PaymentMethodExtension) {
            return null;
        }

        return $extension->getPaymentMethod()->value;
    }
}
