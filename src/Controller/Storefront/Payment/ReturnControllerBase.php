<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Storefront\Payment;

use Kiener\MolliePayments\Controller\Storefront\AbstractStoreFrontController;
use Kiener\MolliePayments\Facade\Controller\PaymentReturnFacade;
use Mollie\Api\Exceptions\ApiException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Response;

class ReturnControllerBase extends AbstractStoreFrontController
{
    /**
     * @var PaymentReturnFacade
     */
    private $returnFacade;

    public function __construct(PaymentReturnFacade $returnFacade)
    {
        $this->returnFacade = $returnFacade;
    }

    /**
     * @throws ApiException
     */
    public function payment(SalesChannelContext $salesChannelContext, string $swTransactionId): ?Response
    {
        return $this->returnFacade->returnAction($swTransactionId, $salesChannelContext->getContext());
    }
}
