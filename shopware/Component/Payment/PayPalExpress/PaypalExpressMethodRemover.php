<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\PayPalExpress;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\Session;
use Mollie\Shopware\Component\Payment\Method\PayPalExpressPayment;
use Mollie\Shopware\Component\Payment\MethodRemover\AbstractPaymentRemover;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PaypalExpressMethodRemover extends AbstractPaymentRemover
{
    /**
     * @param EntityRepository<EntityCollection<OrderEntity>> $orderRepository
     */
    public function __construct(
        private CartService $cartService,
        #[Autowire(service: 'order.repository')]
        private EntityRepository $orderRepository,
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
    ) {
    }

    public function remove(PaymentMethodCollection $paymentMethods, string $orderId, SalesChannelContext $salesChannelContext): PaymentMethodCollection
    {
        $filteredPaymentMethods = $paymentMethods->filter(function (PaymentMethodEntity $paymentMethod) {
            return $paymentMethod->getHandlerIdentifier() === PayPalExpressPayment::class;
        });
        $paypalExpressPaymentMethod = $filteredPaymentMethods->first();

        if (! $paypalExpressPaymentMethod instanceof PaymentMethodEntity) {
            return $paymentMethods;
        }

        $paypalExpressPaymentMethodId = $paypalExpressPaymentMethod->getId();

        $paymentMethods->remove($paypalExpressPaymentMethodId);

        $paypalExpressSettings = $this->settingsService->getPaypalExpressSettings($salesChannelContext->getSalesChannelId());
        if (! $paypalExpressSettings->isEnabled()) {
            return $paymentMethods;
        }

        if (mb_strlen($orderId) === 0) {
            return $this->getPaymentMethodsByCart($salesChannelContext, $paypalExpressPaymentMethod, $filteredPaymentMethods, $paymentMethods);
        }

        return $this->getPaymentMethodsByOrder($orderId, $salesChannelContext, $paymentMethods, $paypalExpressPaymentMethod, $filteredPaymentMethods);
    }

    private function getPaymentMethodsByCart(SalesChannelContext $salesChannelContext,
        PaymentMethodEntity $paypalExpressPaymentMethod,
        PaymentMethodCollection $filteredPaymentMethods,
        PaymentMethodCollection $paymentMethods): PaymentMethodCollection
    {
        $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);
        /** @var ?Session $paypalSession */
        $paypalSession = $cart->getExtension(Mollie::EXTENSION);
        if (! $paypalSession instanceof Session) {
            return $paymentMethods;
        }

        $this->setAuthenticateId($paypalExpressPaymentMethod, $paypalSession->getAuthenticationId());

        return $filteredPaymentMethods;
    }

    private function getPaymentMethodsByOrder(string $orderId, SalesChannelContext $salesChannelContext, PaymentMethodCollection $paymentMethods, PaymentMethodEntity $paypalExpressPaymentMethod, PaymentMethodCollection $filteredPaymentMethods): PaymentMethodCollection
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions');
        $criteria->getAssociation('transactions')->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING))->setLimit(1);

        $orderSearchResult = $this->orderRepository->search($criteria, $salesChannelContext->getContext());
        $orderEntity = $orderSearchResult->first();

        if (! $orderEntity instanceof OrderEntity) {
            return $paymentMethods;
        }

        $orderTransactions = $orderEntity->getTransactions();

        if (! $orderTransactions instanceof OrderTransactionCollection) {
            return $paymentMethods;
        }
        $latestTransaction = $orderTransactions->first();

        if (! $latestTransaction instanceof OrderTransactionEntity) {
            return $paymentMethods;
        }
        /** @var ?Payment $paymentExtension */
        $paymentExtension = $latestTransaction->getExtension(Mollie::EXTENSION);
        if (! $paymentExtension instanceof Payment) {
            return $paymentMethods;
        }
        $authenticationId = $paymentExtension->getAuthenticationId();
        if ($authenticationId === null) {
            return $paymentMethods;
        }

        $this->setAuthenticateId($paypalExpressPaymentMethod, $authenticationId);

        return $filteredPaymentMethods;
    }

    private function setAuthenticateId(PaymentMethodEntity $paypalExpressPaymentMethod, string $authenticationId): void
    {
        $paymentMethodCustomFields = $paypalExpressPaymentMethod->getCustomFields() ?? [];
        $paymentMethodCustomFields[Mollie::EXTENSION] = [
            'authenticationId' => $authenticationId,
        ];
        $paypalExpressPaymentMethod->setCustomFields($paymentMethodCustomFields);
    }
}
