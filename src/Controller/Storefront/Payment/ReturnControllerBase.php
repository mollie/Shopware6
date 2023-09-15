<?php

namespace Kiener\MolliePayments\Controller\Storefront\Payment;

use Kiener\MolliePayments\Facade\Controller\PaymentReturnFacade;
use Mollie\Api\Exceptions\ApiException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReturnControllerBase extends StorefrontController
{
    /**
     * @var PaymentReturnFacade
     */
    private $returnFacade;


    /**
     * @param PaymentReturnFacade $returnFacade
     */
    public function __construct(PaymentReturnFacade $returnFacade)
    {
        $this->returnFacade = $returnFacade;
    }

    /**
     * @Route("/mollie/payment/{swTransactionId}", defaults={"csrf_protected"=false}, name="frontend.mollie.payment", options={"seo"="false"}, methods={"GET", "POST"})
     *
     * @param SalesChannelContext $salesChannelContext
     * @param string $swTransactionId
     * @throws ApiException
     * @return null|Response
     */
    public function payment(SalesChannelContext $salesChannelContext, string $swTransactionId): ?Response
    {
        return $this->returnFacade->returnAction($swTransactionId, $salesChannelContext->getContext());
    }
}
