<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Controller\Storefront\CreditCard;

use Kiener\MolliePayments\Controller\Storefront\CreditCard\CreditCardController;
use MolliePayments\Tests\Fakes\FakeCustomerService;
use MolliePayments\Tests\Fakes\FakeMandateService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class CreditCardControllerTest extends TestCase
{
    public function testResponseNotSuccessWithoutCustomer():void
    {
        $customerService = new FakeCustomerService();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $mandateService = new FakeMandateService();
        $controller = new CreditCardController($customerService, $mandateService, new NullLogger());

        $actualResponse = $controller->storeCardToken($salesChannelContext,'test','test', new Request());
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
        $mandateService = new FakeMandateService();
        $controller = new CreditCardController($customerService, $mandateService, new NullLogger());

        $actualResponse = $controller->storeCardToken($salesChannelContext,'test','test', new Request());
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
        $mandateService = new FakeMandateService();
        $controller = new CreditCardController($customerService, $mandateService, new NullLogger());

        $actualResponse = $controller->storeCardToken($salesChannelContext,'test','test', new Request());
        $expected = new JsonResponse([
            'success' => true,
            'customerId' => 'test',
            'result' => []
        ]);

        $this->assertEquals($expected,$actualResponse);
    }

    public function testResponseNotSuccessWithoutCustomerStoreMandateId():void
    {
        $customerService = new FakeCustomerService();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $mandateService = new FakeMandateService();
        $controller = new CreditCardController($customerService, $mandateService, new NullLogger());

        $actualResponse = $controller->storeMandateId('test','test', $salesChannelContext);
        $expected = new JsonResponse([
            'success' => false,
            'customerId' => 'test',
            'result' => null
        ]);

        $this->assertEquals($expected, $actualResponse);
    }

    public function testResponseNotSuccessWithErrorsStoreMandateId():void
    {
        $customerService = new FakeCustomerService();
        $customerService = $customerService->withFakeCustomer();
        $customerService = $customerService->withSaveMandateIdErrors(['test error']);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $mandateService = new FakeMandateService();
        $controller = new CreditCardController($customerService, $mandateService, new NullLogger());

        $actualResponse = $controller->storeMandateId('test','test', $salesChannelContext);
        $expected = new JsonResponse([
            'success' => false,
            'customerId' => 'test',
            'result' => ['test error']
        ]);

        $this->assertEquals($expected, $actualResponse);
    }

    public function testResponseSuccessStoreMandateId():void{
        $customerService = new FakeCustomerService();
        $customerService = $customerService->withFakeCustomer();

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $mandateService = new FakeMandateService();
        $controller = new CreditCardController($customerService, $mandateService, new NullLogger());

        $actualResponse = $controller->storeMandateId('test','test', $salesChannelContext);
        $expected = new JsonResponse([
            'success' => true,
            'customerId' => 'test',
            'result' => []
        ]);

        $this->assertEquals($expected, $actualResponse);
    }

    public function testResponseNotSuccessWithoutCustomerRevokeMandate():void
    {
        $customerService = new FakeCustomerService();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $mandateService = new FakeMandateService();
        $controller = new CreditCardController($customerService, $mandateService, new NullLogger());

        $actualResponse = $controller->revokeMandate('test','test', $salesChannelContext);
        $expected = new JsonResponse([
            'success' => false,
            'customerId' => 'test',
            'mandateId' => 'test',
            'result' => null
        ]);

        $this->assertEquals($expected, $actualResponse);
    }

/**
 *
 */
    public function testResponseNotSuccessWithErrorsRevokeMandate():void
    {
        $customerService = new FakeCustomerService(true);
        $customerService = $customerService->withFakeCustomer();

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $mandateService = new FakeMandateService(true);
        $controller = new CreditCardController($customerService, $mandateService, new NullLogger());

        $actualResponse = $controller->revokeMandate('test','test', $salesChannelContext);
        $expected = new JsonResponse([
            'success' => false,
            'customerId' => 'test',
            'mandateId' => 'test',
            'result' => 'Error when removing mandate',
        ]);

        $this->assertEquals($expected, $actualResponse);
    }

    public function testResponseSuccessRevokeMandate():void{
        $customerService = new FakeCustomerService();
        $customerService = $customerService->withFakeCustomer();

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $mandateService = new FakeMandateService();
        $controller = new CreditCardController($customerService, $mandateService, new NullLogger());

        $actualResponse = $controller->revokeMandate('test','test', $salesChannelContext);
        $expected = new JsonResponse([
            'success' => true,
            'customerId' => 'test',
            'mandateId' => 'test',
            'result' => null,
        ]);

        $this->assertEquals($expected, $actualResponse);
    }
}
