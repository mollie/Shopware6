<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\Payment;

use Kiener\MolliePayments\Facade\Controller\PaymentReturnFacade;
use Mollie\Api\Exceptions\ApiException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReturnControllerBase extends AbstractController
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
     * @Route("/api/mollie/payment/return/{swTransactionId}", name="api.mollie.payment-return", methods={"GET", "POST"})
     *
     * @param string $swTransactionId
     * @param Context $context
     * @throws ApiException
     * @return null|Response
     */
    public function returnAction(string $swTransactionId, Context $context): ?Response
    {
        return $this->returnFacade->returnAction($swTransactionId, $context);
    }

    /**
     * @Route("/api/v{version}/mollie/payment/return/{swTransactionId}", name="api.mollie.payment-return-legacy", methods={"GET", "POST"})
     *
     * @param string $swTransactionId
     * @param Context $context
     * @throws ApiException
     * @return null|Response
     */
    public function returnActionLegacy(string $swTransactionId, Context $context): ?Response
    {
        return $this->returnFacade->returnAction($swTransactionId, $context);
    }
}
