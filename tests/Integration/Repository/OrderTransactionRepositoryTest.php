<?php
declare(strict_types=1);

namespace Mollie\Integration\Repository;

use Doctrine\DBAL\Connection;
use Kiener\MolliePayments\Compatibility\VersionCompare;
use Kiener\MolliePayments\Handler\Method\PayPalPayment;
use Mollie\Integration\Data\CheckoutTestBehaviour;
use Mollie\Integration\Data\CustomerTestBehaviour;
use Mollie\Integration\Data\MolliePageTestBehaviour;
use Mollie\Integration\Data\OrderTestBehaviour;
use Mollie\Integration\Data\PaymentMethodTestBehaviour;
use Mollie\Integration\Data\ProductTestBehaviour;
use Mollie\Shopware\Repository\OrderTransactionRepository;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\CashPayment;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
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

    protected function setUp(): void
    {
        $versionCompare = $this->getContainer()->get(VersionCompare::class);
        if ($versionCompare->lt('6.5')) {
            $this->markTestSkipped(
                'We have issues with shopware version below 6.5, we skip the tests for now'
            );
        }
        $this->getContainer()->get(Connection::class)->setAutoCommit(true);
        $salesChannelContext = $this->getDefaultSalesChannelContext();

        $paypalPaymentMethod = $this->getPaymentMethodByIdentifier(PayPalPayment::class, $salesChannelContext->getContext());

        $this->activatePaymentMethod($paypalPaymentMethod, $salesChannelContext->getContext());
        $this->assignPaymentMethodToSalesChannel($paypalPaymentMethod, $salesChannelContext->getSalesChannel(), $salesChannelContext->getContext());

        $salesChannelContext = $this->loginOrCreateAccount('test@mollie.com', $salesChannelContext);

        $salesChannelContext = $this->createOrderWithCashPayment($salesChannelContext);
        $this->assertNotNull($salesChannelContext->getCustomer());

        $salesChannelContext = $this->createMollieOrderWithPaymentMethod('paid', $paypalPaymentMethod, $salesChannelContext);

        $salesChannelContext = $this->createMollieOrderWithPaymentMethod('paid', $paypalPaymentMethod, $salesChannelContext);

        $latestOrderId = $this->getLatestOrderId($salesChannelContext->getContext());
        $this->updateOrder($latestOrderId, [
            'orderDateTime' => (new \DateTime())->modify('-10 minutes')->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ], $salesChannelContext->getContext());
    }

    protected function tearDown(): void
    {
        $versionCompare = $this->getContainer()->get(VersionCompare::class);
        if ($versionCompare->lt('6.5')) {
            $this->markTestSkipped(
                'We have issues with shopware version below 6.5, we skip the tests for now'
            );
        }
        $result = $this->deleteAllOrders(Context::createDefaultContext());
        $this->assertNotNull($result);
    }

    public function testMollieTransactionsAreLoaded(): void
    {
        $salesChannelContext = $this->getDefaultSalesChannelContext();

        $orderTransactionRepository = $this->getContainer()->get(OrderTransactionRepository::class);
        $searchResult = $orderTransactionRepository->findOpenTransactions($salesChannelContext->getContext());

        $this->assertSame(1, $searchResult->getTotal());
    }

    private function createMollieOrderWithPaymentMethod(string $status, PaymentMethodEntity $paymentMethod, SalesChannelContext $salesChannelContext): SalesChannelContext
    {
        $salesChannelContext = $this->setPaymentMethod($paymentMethod, $salesChannelContext);
        $this->assertNotNull($salesChannelContext->getCustomer());
        $this->addItemToCart('SWDEMO10007.1', $salesChannelContext);

        /** @var RedirectResponse $response */
        $response = $this->startCheckout($salesChannelContext);

        $this->assertStringContainsString('mollie.com', $response->getTargetUrl());

        $this->selectMolliePaymentStatus($status, $response->getTargetUrl());

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

        return $salesChannelContext;
    }
}
