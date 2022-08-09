<?php

namespace Kiener\MolliePayments\Controller\Routes\ApplePayDirect;

use Kiener\MolliePayments\Controller\Routes\ApplePayDirect\Struct\ApplePaySession;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\DomainExtractor;
use Kiener\MolliePayments\Service\ShopService;
use Mollie\Api\Exceptions\ApiException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class CreateSessionRoute
{

    /**
     * @var MollieApiFactory
     */
    private $mollieApiFactory;

    /**
     * @var ShopService
     */
    private $shopService;


    /**
     * @param MollieApiFactory $mollieApiFactory
     * @param ShopService $shopService
     */
    public function __construct(MollieApiFactory $mollieApiFactory, ShopService $shopService)
    {
        $this->mollieApiFactory = $mollieApiFactory;
        $this->shopService = $shopService;
    }


    /**
     * @param string $validationURL
     * @param SalesChannelContext $context
     * @return ApplePaySession
     * @throws ApiException
     */
    public function createPaymentSession(string $validationURL, SalesChannelContext $context): ApplePaySession
    {
        # make sure to get rid of any http prefixes or
        # also any sub shop slugs like /de or anything else
        # that would NOT work with Mollie and Apple Pay!
        $domainExtractor = new DomainExtractor();
        $domain = $domainExtractor->getCleanDomain($this->shopService->getShopUrl(true));

        # we always have to use the LIVE api key for
        # our first domain validation for Apple Pay!
        # the rest will be done with our test API key (if test mode active), or also Live API key (no test mode)
        $liveClient = $this->mollieApiFactory->getLiveClient($context->getSalesChannel()->getId());

        $paymentSession = $liveClient->wallets->requestApplePayPaymentSession($domain, $validationURL);

        return new ApplePaySession($paymentSession);
    }

}
