<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Subscription;

use Mollie\Shopware\Component\Subscription\SubscriptionLineItemsResolver;
use Mollie\Shopware\Component\Subscription\SubscriptionLineItemsResolverInterface;
use Mollie\Shopware\Integration\Data\CheckoutTestBehaviour;
use Mollie\Shopware\Integration\Data\CustomerTestBehaviour;
use Mollie\Shopware\Integration\Data\OrderTestBehaviour;
use Mollie\Shopware\Integration\Data\PaymentMethodTestBehaviour;
use Mollie\Shopware\Integration\Data\ProductTestBehaviour;
use Mollie\Shopware\Integration\Data\ShopwareTestBehaviour;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\CashPayment;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;

final class SubscriptionLineItemsResolverTest extends TestCase
{
    use ShopwareTestBehaviour;
    use IntegrationTestBehaviour;
    use CustomerTestBehaviour;
    use ProductTestBehaviour;
    use CheckoutTestBehaviour;
    use PaymentMethodTestBehaviour;
    use OrderTestBehaviour;

    public function testResolveLineItemsReturnsCartLineItemsForEmptyOrderId(): void
    {
        $salesChannelContext = $this->getDefaultSalesChannelContext();
        $this->addItemToCart('SWDEMO10007.1', $salesChannelContext);

        $resolver = $this->getResolver();

        $resolved = $resolver->resolveLineItems('', $salesChannelContext);

        $this->assertInstanceOf(LineItemCollection::class, $resolved);
        $this->assertGreaterThan(0, $resolved->count());
    }

    public function testResolveLineItemsReturnsOrderLineItemsForExistingOrder(): void
    {
        $salesChannelContext = $this->getDefaultSalesChannelContext();

        $cashPaymentMethod = $this->getPaymentMethodByIdentifier(CashPayment::class, $salesChannelContext->getContext());
        $this->activatePaymentMethod($cashPaymentMethod, $salesChannelContext->getContext());
        $this->assignPaymentMethodToSalesChannel($cashPaymentMethod, $salesChannelContext->getSalesChannel(), $salesChannelContext->getContext());

        $customerId = $this->getUserIdByEmail('cypress@mollie.com', $salesChannelContext);
        $salesChannelContext = $this->getDefaultSalesChannelContext(options: [
            SalesChannelContextService::CUSTOMER_ID => $customerId,
            SalesChannelContextService::PAYMENT_METHOD_ID => $cashPaymentMethod->getId(),
        ]);

        $this->addItemToCart('SWDEMO10007.1', $salesChannelContext);
        $this->startCheckout($salesChannelContext);

        $orderId = $this->getLatestOrderId($salesChannelContext->getContext());
        $this->assertNotNull($orderId);

        $resolved = $this->getResolver()->resolveLineItems($orderId, $salesChannelContext);

        $this->assertInstanceOf(OrderLineItemCollection::class, $resolved);
        $this->assertGreaterThan(0, $resolved->count());
    }

    public function testResolveLineItemsReturnsEmptyCollectionForUnknownOrderId(): void
    {
        $salesChannelContext = $this->getDefaultSalesChannelContext();

        $resolved = $this->getResolver()->resolveLineItems(Uuid::randomHex(), $salesChannelContext);

        $this->assertInstanceOf(LineItemCollection::class, $resolved);
        $this->assertCount(0, $resolved);
    }

    private function getResolver(): SubscriptionLineItemsResolverInterface
    {
        return $this->getContainer()->get(SubscriptionLineItemsResolver::class);
    }
}
