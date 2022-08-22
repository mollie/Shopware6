<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\Webhook;

use Kiener\MolliePayments\Controller\Storefront\Webhook\NotificationFacade;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @RouteScope(scopes={"api"})
 */
class WebhookController extends AbstractController
{

    /**
     * @var NotificationFacade
     */
    private $notificationFacade;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param NotificationFacade $notificationFacade
     * @param LoggerInterface $logger
     */
    public function __construct(NotificationFacade $notificationFacade, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->notificationFacade = $notificationFacade;
    }


    /**
     * @Route("/api/mollie/webhook/{transactionId}", defaults={"auth_required"=false, "auth_enabled"=false}, name="api.mollie.webhook", methods={"GET", "POST"})
     *
     * @param string $transactionId
     * @param Request $request
     * @param Context $context
     * @return JsonResponse
     */
    public function webhookAction(string $transactionId, Request $request, Context $context): JsonResponse
    {
        try {

            $this->notificationFacade->onNotify($transactionId, $context);

            return new JsonResponse([
                'success' => true
            ]);

        } catch (\Throwable $ex) {

            $this->logger->error(
                'Error in Mollie Webhook for Transaction ' . $transactionId,
                [
                    'error' => $ex->getMessage()
                ]
            );

            return new JsonResponse([
                'success' => false,
                'error' => $ex->getMessage()
            ],
                422
            );
        }
    }

    /**
     * @Route("/api/v{version}/mollie/webhook/{transactionId}", defaults={"auth_required"=false, "auth_enabled"=false}, name="api.mollie.webhook-legacy", methods={"GET", "POST"})
     *
     * @param string $transactionId
     * @param Request $request
     * @param Context $context
     * @return JsonResponse
     */
    public function webhookLegacyAction(string $transactionId, Request $request, Context $context): JsonResponse
    {
        return $this->webhookAction($transactionId, $request, $context);
    }

}
