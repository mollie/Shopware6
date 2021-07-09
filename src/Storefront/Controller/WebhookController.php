<?php

namespace Kiener\MolliePayments\Storefront\Controller;

use Kiener\MolliePayments\Facade\Notifications\NotificationFacade;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\SettingsService;
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
     * @var LoggerService
     */
    private $logger;


    /**
     * @param SettingsService $settingsService
     * @param NotificationFacade $notificationFacade
     * @param LoggerService $logger
     */
    public function __construct(SettingsService $settingsService, NotificationFacade $notificationFacade, LoggerService $logger)
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

            if ($settings->isDebugMode()) {

                $this->logger->addEntry(
                    sprintf('Webhook for transaction %s is triggered.', $transactionId),
                    $context->getContext(),
                    null,
                    [
                        'transactionId' => $transactionId,
                    ]
                );
            }


            $this->notificationFacade->onNotify($transactionId, $settings, $context);

            return new JsonResponse(['success' => true]);

        } catch (\Throwable $ex) {

            $this->logger->addEntry(
                $ex->getMessage(),
                $context->getContext(),
                null,
                [
                    'function' => 'webhook',
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
