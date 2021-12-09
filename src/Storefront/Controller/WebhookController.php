<?php

namespace Kiener\MolliePayments\Storefront\Controller;

use Kiener\MolliePayments\Facade\Notifications\NotificationFacade;
use Kiener\MolliePayments\Service\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class WebhookController extends StorefrontController
{

    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var NotificationFacade
     */
    private $notificationFacade;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param SettingsService $settingsService
     * @param NotificationFacade $notificationFacade
     * @param LoggerInterface $logger
     */
    public function __construct(SettingsService $settingsService, NotificationFacade $notificationFacade, LoggerInterface $logger)
    {
        $this->settingsService = $settingsService;
        $this->logger = $logger;
        $this->notificationFacade = $notificationFacade;
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/webhook/{transactionId}", defaults={"csrf_protected"=false}, name="frontend.mollie.webhook",
     *                                           options={"seo"="false"}, methods={"GET", "POST"})
     *
     * @param SalesChannelContext $context
     * @param                     $transactionId
     *
     * @return JsonResponse
     */
    public function webhookCall(SalesChannelContext $context, $transactionId): JsonResponse
    {
        try {

            $settings = $this->settingsService->getSettings($context->getSalesChannel()->getId());

            $this->notificationFacade->onNotify($transactionId, $settings, $context);

            return new JsonResponse(['success' => true]);

        } catch (\Throwable $ex) {

            $this->logger->error(
                'Error in Mollie Webhook for Transaction ' . $transactionId,
                [
                    'error' => $ex->getMessage()
                ]
            );

            return new JsonResponse(
                [
                    'success' => false,
                    'error' => $ex->getMessage()
                ],
                422
            );
        }
    }


}
