<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Controller\Storefront\CreditCard;

use Kiener\MolliePayments\Controller\Storefront\CreditCard\CreditCardController;
use MolliePayments\Tests\Fakes\FakeCustomerService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;

final class CreditCardControllerTest extends TestCase
{
    public function testResponseNotSuccessWithoutCustomer():void
    {
        $customerService = new FakeCustomerService();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $controller = new CreditCardController($customerService);

        $actualResponse = $controller->storeCardToken($salesChannelContext,'test','test');
        $expected = new JsonResponse([
            'success' => false,
            'customerId' => 'test',
            'result' => null
        ]);

        $this->assertEquals($expected,$actualResponse);
    }
    public function testResponseNotSuccessWithErrors():void
    {
        $customerService = new FakeCustomerService();
        $customerService = $customerService->withFakeCustomer();
        $customerService = $customerService->withCardTokenErrors(['test error']);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $controller = new CreditCardController($customerService);

        $actualResponse = $controller->storeCardToken($salesChannelContext,'test','test');
        $expected = new JsonResponse([
            'success' => false,
            'customerId' => 'test',
            'result' => ['test error']
        ]);

        $this->assertEquals($expected,$actualResponse);
    }

    public function testResponseSuccess():void{
        $customerService = new FakeCustomerService();
        $customerService = $customerService->withFakeCustomer();

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $controller = new CreditCardController($customerService);

        $actualResponse = $controller->storeCardToken($salesChannelContext,'test','test');
        $expected = new JsonResponse([
            'success' => true,
            'customerId' => 'test',
            'result' => []
        ]);

        $this->assertEquals($expected,$actualResponse);
    }
}