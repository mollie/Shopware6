<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api;

use Kiener\MolliePayments\Facade\MollieRefundFacade;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class RefundManagerController extends AbstractController
{
    /**
     * @var MollieRefundFacade
     */
    private $refundFacade;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param MollieRefundFacade $refundFacade
     * @param LoggerInterface $logger
     */
    public function __construct(MollieRefundFacade $refundFacade, LoggerInterface $logger)
    {
        $this->refundFacade = $refundFacade;
        $this->logger = $logger;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/mollie/refund-manager/start",
     *         defaults={"auth_enabled"=true}, name="api.mollie.refund.order", methods={"GET"})
     * @param QueryDataBag $query
     * @param Context $context
     * @return JsonResponse
     */
    public function refundOrder(QueryDataBag $query, Context $context): JsonResponse
    {

        return $this->json([
            'success' => true,
        ]);
    }

}
