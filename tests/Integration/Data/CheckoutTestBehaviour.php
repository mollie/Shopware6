<?php
declare(strict_types=1);

namespace Mollie\Integration\Data;

use Kiener\MolliePayments\Controller\Storefront\Payment\ReturnControllerBase;
use PHPUnit\Framework\Assert;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\Controller\PaymentController;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\CartLineItemController;
use Shopware\Storefront\Controller\CheckoutController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;

trait CheckoutTestBehaviour
{
    use IntegrationTestBehaviour;
    use ProductTestBehaviour;
    use RequestTestBehaviour;

    public function addItemToCart(string $productNumber, SalesChannelContext $salesChannelContext, int $quantity = 1): Response
    {
        $cartService = $this->getContainer()->get(CartService::class);
        $cart = $cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);
        /** @var CartLineItemController $cartItemAddRoute */
        $cartLineItemController = $this->getContainer()->get(CartLineItemController::class);

        $product = $this->getProductByNumber($productNumber, $salesChannelContext->getContext());
        $request = $this->createStoreFrontRequest($salesChannelContext);

        $requestDataBag = new RequestDataBag();

        $lineItemDataBag = new RequestDataBag([
            'id' => $product->getId(),
            'referenceId' => $product->getId(),
            'referencedId' => $product->getId(), //this is required for shopware 6.5.5.2
            'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            'quantity' => $quantity,
        ]);

        $requestDataBag->set('lineItems', [
            $product->getId() => $lineItemDataBag
        ]);

        return $cartLineItemController->addLineItems($cart, $requestDataBag, $request, $salesChannelContext);
    }

    public function setPaymentMethod(PaymentMethodEntity $paymentMethod, SalesChannelContext $salesChannelContext): SalesChannelContext
    {
        $options = [
            SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethod->getId(),
            SalesChannelContextService::CUSTOMER_ID => $salesChannelContext->getCustomer()->getId(),
        ];

        return $this->getSalesChannelContext($salesChannelContext->getSalesChannel(), $options);
    }

    public function startCheckout(SalesChannelContext $salesChannelContext): Response
    {
        $request = $this->createStoreFrontRequest($salesChannelContext);
        $checkoutController = $this->getContainer()->get(CheckoutController::class);
        $requestDataBag = new RequestDataBag();
        $requestDataBag->set('tos', true);

        $response = $checkoutController->order($requestDataBag, $salesChannelContext, $request);
        /** @var FlashBag $flashBag */
        $flashBag = $request->getSession()->getBag('flashes');
        $flashBagData = $flashBag->peekAll();
        $dangerErrors = $flashBagData['danger'] ?? [];
        $warningErrors = $flashBagData['warning'] ?? [];
        $hasFlashes = count($dangerErrors) > 0 || count($warningErrors) > 0;

        Assert::assertFalse($hasFlashes, 'Create order has error messages ' . print_r($dangerErrors + $warningErrors, true));

        return $response;
    }

    public function findCurrencyByIso(string $currencyIso, SalesChannelContext $salesChannelContext): CurrencyEntity
    {
        /** @var EntityRepository $currencyRepository */
        $currencyRepository = $this->getContainer()->get('currency.repository');
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('isoCode', $currencyIso))
        ;

        return $currencyRepository->search($criteria, $salesChannelContext->getContext())->first();
    }

    public function finishCheckout(string $paymentUrl, SalesChannelContext $salesChannelContext): Response
    {
        $matches = [];
        preg_match('/mollie\/payment\/(?<paymentId>.*)/m', $paymentUrl, $matches);
        $paymentId = $matches['paymentId'];

        $returnController = $this->getContainer()->get(ReturnControllerBase::class);
        /** @var RedirectResponse $response */
        $response = $returnController->payment($salesChannelContext, $paymentId);
        $redirectLocation = $response->getTargetUrl();
        Assert::assertSame(302, $response->getStatusCode());
        Assert::assertStringContainsString('payment/finalize-transaction', $redirectLocation);

        $queryString = parse_url($redirectLocation, PHP_URL_QUERY);
        $urlParameters = [];
        parse_str($queryString, $urlParameters);

        $request = $this->createStoreFrontRequest($salesChannelContext);
        $request->request->set('_sw_payment_token', $urlParameters['_sw_payment_token']);
        $paymentController = $this->getContainer()->get(PaymentController::class);

        return $paymentController->finalizeTransaction($request);
    }
}
