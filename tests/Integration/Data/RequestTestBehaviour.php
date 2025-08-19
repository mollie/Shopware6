<?php
declare(strict_types=1);

namespace Mollie\Integration\Data;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

trait RequestTestBehaviour
{
    use IntegrationTestBehaviour;
    use SalesChannelTestBehaviour;

    public function createStoreFrontRequest(SalesChannelContext $salesChannelContext, string $method = Request::METHOD_GET): Request
    {
        $request = Request::create('https://mollie-local.diwc.de/', $method);

        $request->setSession($this->getSession());

        $requestTransformer = $this->getContainer()->get(RequestTransformer::class);

        $request = $requestTransformer->transform($request);

        $salesChannel = $salesChannelContext->getSalesChannel();
        $customer = $salesChannelContext->getCustomer();

        if ($customer instanceof CustomerEntity) {
            $request->attributes->set(SalesChannelContextService::CUSTOMER_ID, $customer->getId());
        }
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, $salesChannel->getId());

        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $salesChannelContext->getToken());

        $requestStack = $this->getContainer()->get(RequestStack::class);
        $requestStack->push($request);

        return $request;

        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['storefront']);

        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST, true);

        //  $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID, $salesChannel->getLanguage()->getId());

        // $request->setMethod($method);
        $request->setSession($this->getSession());

        $requestStack = $this->getContainer()->get(RequestStack::class);
        $requestStack->push($request);

        return $request;
    }
}
