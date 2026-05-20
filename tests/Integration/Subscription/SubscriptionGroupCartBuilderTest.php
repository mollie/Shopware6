<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Subscription;

use Mollie\Shopware\Component\Payment\Method\EpsPayment;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzer;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzerInterface;
use Mollie\Shopware\Component\Subscription\SubscriptionGroupCart;
use Mollie\Shopware\Component\Subscription\SubscriptionGroupCartBuilder;
use Mollie\Shopware\Component\Subscription\SubscriptionGroupCartBuilderInterface;
use Mollie\Shopware\Integration\Data\CheckoutTestBehaviour;
use Mollie\Shopware\Integration\Data\CustomerTestBehaviour;
use Mollie\Shopware\Integration\Data\OrderTestBehaviour;
use Mollie\Shopware\Integration\Data\PaymentMethodTestBehaviour;
use Mollie\Shopware\Integration\Data\ProductTestBehaviour;
use Mollie\Shopware\Integration\Data\ShopwareTestBehaviour;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class SubscriptionGroupCartBuilderTest extends TestCase
{
    use ShopwareTestBehaviour;
    use IntegrationTestBehaviour;
    use CustomerTestBehaviour;
    use ProductTestBehaviour;
    use CheckoutTestBehaviour;
    use PaymentMethodTestBehaviour;
    use OrderTestBehaviour;

    public function testBuildGroupCartProducesOneCartPerIntervalGroup(): void
    {
        $orderId = $this->createMollieOrderWithSubscriptionProducts();
        $context = $this->getDefaultSalesChannelContext()->getContext();

        $order = $this->loadOrderWithLineItems($orderId, $context);

        $orderLineItems = $order->getLineItems();
        $this->assertInstanceOf(OrderLineItemCollection::class, $orderLineItems);

        $groups = $this->getLineItemAnalyzer()->groupSubscriptionLineItemsByInterval($orderLineItems);
        $this->assertCount(2, $groups, 'Expected two interval groups (daily + weekly).');

        foreach ($groups as $intervalKey => $expectedLineItems) {
            $groupCart = $this->getBuilder()->buildGroupCart($order, (string) $intervalKey, $context);

            $this->assertInstanceOf(SubscriptionGroupCart::class, $groupCart, 'Builder returned null for ' . $intervalKey);

            $cartLineItems = $groupCart->getCart()->getLineItems();
            $this->assertCount(1, $cartLineItems, 'Group ' . $intervalKey . ' should produce a cart with exactly one product.');

            $expectedProductId = $expectedLineItems[0]->getReferencedId();
            $this->assertSame($expectedProductId, $cartLineItems->first()->getReferencedId());
        }
    }

    public function testBuildGroupCartReturnsNullForUnknownInterval(): void
    {
        $orderId = $this->createMollieOrderWithSubscriptionProducts();
        $context = $this->getDefaultSalesChannelContext()->getContext();

        $order = $this->loadOrderWithLineItems($orderId, $context);

        $groupCart = $this->getBuilder()->buildGroupCart($order, '5 light-years', $context);

        $this->assertNull($groupCart);
    }

    private function createMollieOrderWithSubscriptionProducts(): string
    {
        $salesChannelContext = $this->getDefaultSalesChannelContext();

        $epsPaymentMethod = $this->getPaymentMethodByIdentifier(EpsPayment::class, $salesChannelContext->getContext());
        $this->activatePaymentMethod($epsPaymentMethod, $salesChannelContext->getContext());
        $this->assignPaymentMethodToSalesChannel($epsPaymentMethod, $salesChannelContext->getSalesChannel(), $salesChannelContext->getContext());

        $customerId = $this->getUserIdByEmail('cypress@mollie.com', $salesChannelContext);
        $salesChannelContext = $this->getDefaultSalesChannelContext(options: [
            SalesChannelContextService::CUSTOMER_ID => $customerId,
            SalesChannelContextService::PAYMENT_METHOD_ID => $epsPaymentMethod->getId(),
        ]);

        $this->addItemToCart('MOL_SUB_1', $salesChannelContext);
        $this->addItemToCart('MOL_SUB_2', $salesChannelContext);

        /** @var RedirectResponse $response */
        $response = $this->startCheckout($salesChannelContext);
        $this->assertStringContainsString('mollie.com', $response->getTargetUrl());

        $orderId = $this->getLatestOrderId($salesChannelContext->getContext());
        $this->assertNotNull($orderId);

        return $orderId;
    }

    private function loadOrderWithLineItems(string $orderId, Context $context): OrderEntity
    {
        $orderRepository = $this->getContainer()->get('order.repository');
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('transactions');
        $criteria->addAssociation('deliveries.shippingOrderAddress');
        $criteria->addAssociation('billingAddress');

        $order = $orderRepository->search($criteria, $context)->first();
        $this->assertInstanceOf(OrderEntity::class, $order);

        return $order;
    }

    private function getBuilder(): SubscriptionGroupCartBuilderInterface
    {
        return $this->getContainer()->get(SubscriptionGroupCartBuilder::class);
    }

    private function getLineItemAnalyzer(): LineItemAnalyzerInterface
    {
        return $this->getContainer()->get(LineItemAnalyzer::class);
    }
}
