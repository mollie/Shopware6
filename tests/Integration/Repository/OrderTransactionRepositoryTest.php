<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Repository;


use Mollie\Shopware\Component\Payment\Method\PayPalPayment;
use Mollie\Shopware\Integration\Data\CheckoutTestBehaviour;
use Mollie\Shopware\Integration\Data\CustomerTestBehaviour;
use Mollie\Shopware\Integration\Data\MolliePageTestBehaviour;
use Mollie\Shopware\Integration\Data\OrderTestBehaviour;
use Mollie\Shopware\Integration\Data\PaymentMethodTestBehaviour;
use Mollie\Shopware\Integration\Data\ProductTestBehaviour;
use Mollie\Shopware\Repository\OrderTransactionRepository;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\CashPayment;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;

class OrderTransactionRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomerTestBehaviour;
    use ProductTestBehaviour;
    use CheckoutTestBehaviour;
    use PaymentMethodTestBehaviour;
    use MolliePageTestBehaviour;
    use OrderTestBehaviour;
    private array $createdOrders = [];

    /** This test make sure that only valid open orders are found */
    public function testMollieTransactionsAreLoaded(): void
    {
        $this->createdOrders = [];
        $this->createTestOrders();
        $salesChannelContext = $this->getDefaultSalesChannelContext();

        $orderTransactionRepository = $this->getContainer()->get(OrderTransactionRepository::class);
        $searchResult = $orderTransactionRepository->findOpenTransactions($salesChannelContext->getContext());

        $this->assertSame(1, $searchResult->getTotal());
        $this->deleteAllOrders($this->createdOrders, new Context(new SystemSource()));
    }

    public function testFindByTransactionIdReturnsOrderTransaction(): void
    {
        $this->createdOrders = [];

        $salesChannelContext = $this->getSalesChannelContestWithCustomer();
        $salesChannelContext = $this->createOrderWithCashPayment($salesChannelContext);
        $latestOrderId = $this->createdOrders[0];
        $orderEntity = $this->getOrder($latestOrderId, $salesChannelContext->getContext());
        $transactionId = $orderEntity->getTransactions()->first()->getId();

        $orderTransactionRepository = $this->getContainer()->get(OrderTransactionRepository::class);
        $transaction = $orderTransactionRepository->findById($transactionId, $salesChannelContext->getContext());
        $this->assertInstanceOf(OrderTransactionEntity::class, $transaction);
        $this->assertSame($transaction->getId(), $transactionId);
        $this->deleteAllOrders($this->createdOrders, new Context(new SystemSource()));
    }

    /**
     * create a non mollie order, a normal mollie order and order which is older than 10 minutes.
     * when we search for open orders, we will find this one which is older than 10 minutes
     */
    private function createTestOrders(): void
    {
        $salesChannelContext = $this->getDefaultSalesChannelContext();

        $paypalPaymentMethod = $this->getPaymentMethodByIdentifier(PayPalPayment::class, $salesChannelContext->getContext());

        $this->activatePaymentMethod($paypalPaymentMethod, $salesChannelContext->getContext());
        $this->assignPaymentMethodToSalesChannel($paypalPaymentMethod, $salesChannelContext->getSalesChannel(), $salesChannelContext->getContext());

        $salesChannelContext = $this->getSalesChannelContestWithCustomer($salesChannelContext);

        $salesChannelContext = $this->createOrderWithCashPayment($salesChannelContext);
        $this->assertNotNull($salesChannelContext->getCustomer());

        $salesChannelContext = $this->createMollieOrderWithPaymentMethod($paypalPaymentMethod, $salesChannelContext);
        $latestOrderId = $this->getLatestOrderId($salesChannelContext->getContext());
        $this->createdOrders[] = $latestOrderId;
        $salesChannelContext = $this->createMollieOrderWithPaymentMethod($paypalPaymentMethod, $salesChannelContext);

        $latestOrderId = $this->getLatestOrderId($salesChannelContext->getContext());
        $this->createdOrders[] = $latestOrderId;
        $this->updateOrder($latestOrderId, [
            'orderDateTime' => (new \DateTime())->modify('-10 minutes')->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ], $salesChannelContext->getContext());
    }

    private function getSalesChannelContestWithCustomer(?SalesChannelContext $salesChannelContext = null): SalesChannelContext
    {
        if ($salesChannelContext === null) {
            $salesChannelContext = $this->getDefaultSalesChannelContext();
        }

        $customerId = $this->loginOrCreateAccount('test@mollie.com', $salesChannelContext);

        return $this->getDefaultSalesChannelContext(options: [
            SalesChannelContextService::CUSTOMER_ID => $customerId
        ]);
    }

    private function createMollieOrderWithPaymentMethod(PaymentMethodEntity $paymentMethod, SalesChannelContext $salesChannelContext): SalesChannelContext
    {
        $salesChannelContext = $this->setPaymentMethod($paymentMethod, $salesChannelContext);
        $this->assertNotNull($salesChannelContext->getCustomer());
        $this->addItemToCart('SWDEMO10007.1', $salesChannelContext);

        /** @var RedirectResponse $response */
        $response = $this->startCheckout($salesChannelContext);

        $this->assertStringContainsString('mollie.com', $response->getTargetUrl());

        return $salesChannelContext;
    }

    private function createOrderWithCashPayment(SalesChannelContext $salesChannelContext): SalesChannelContext
    {
        $cashPaymentMethod = $this->getPaymentMethodByIdentifier(CashPayment::class, $salesChannelContext->getContext());

        $this->activatePaymentMethod($cashPaymentMethod, $salesChannelContext->getContext());
        $this->assignPaymentMethodToSalesChannel($cashPaymentMethod, $salesChannelContext->getSalesChannel(), $salesChannelContext->getContext());

        $salesChannelContext = $this->setPaymentMethod($cashPaymentMethod, $salesChannelContext);
        $this->addItemToCart('SWDEMO10007.1', $salesChannelContext);
        /** @var RedirectResponse $response */
        $response = $this->startCheckout($salesChannelContext);
        $urlParts = [];
        $queryString = parse_url($response->getTargetUrl(), PHP_URL_QUERY);
        parse_str($queryString, $urlParts);
        $this->createdOrders[] = $urlParts['orderId'];

        return $salesChannelContext;
    }
}
