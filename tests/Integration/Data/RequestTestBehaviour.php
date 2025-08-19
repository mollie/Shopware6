<?php
declare(strict_types=1);

namespace Mollie\Integration\Data;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
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
        $salesChannel = $salesChannelContext->getSalesChannel();
        $domain = $salesChannel->getDomains()->filter(function (SalesChannelDomainEntity $domain) {
            return str_starts_with($domain->getUrl(), 'https');
        })->first();

        $request = Request::create($domain->getUrl(), $method);

        $request->setSession($this->getSession());

        $requestTransformer = $this->getContainer()->get(RequestTransformer::class);

        $request = $requestTransformer->transform($request);

        $customer = $salesChannelContext->getCustomer();

        if ($customer instanceof CustomerEntity) {
            $request->attributes->set(SalesChannelContextService::CUSTOMER_ID, $customer->getId());
        }
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, $salesChannel->getId());

        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $salesChannelContext->getToken());

        $router = $this->getContainer()->get('router');
        $context = $router->getContext();
        $router->setContext($context->fromRequest($request));

        $requestStack = $this->getContainer()->get(RequestStack::class);
        $requestStack->push($request);

        return $request;
    }
}
