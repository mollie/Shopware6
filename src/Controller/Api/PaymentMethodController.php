<?php

namespace Kiener\MolliePayments\Controller\Api;

use Kiener\MolliePayments\Service\PaymentMethodService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

class PaymentMethodController extends AbstractController
{
    /**
     * @var PaymentMethodService
     */
    private $paymentMethodService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param PaymentMethodService $paymentMethodService
     * @param LoggerInterface $logger
     */
    public function __construct(PaymentMethodService $paymentMethodService, LoggerInterface $logger)
    {
        $this->paymentMethodService = $paymentMethodService;
        $this->logger = $logger;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/mollie/payment-method/update-methods",
     *         defaults={"auth_enabled"=true}, name="api.mollie.payment-method.update-methods", methods={"GET"})
     *
     * @param Context $context
     * @return JsonResponse
     */
    public function updatePaymentMethods(Context $context): JsonResponse
    {
        try {
            $this->paymentMethodService->installAndActivatePaymentMethods($context);
        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage());

            return $this->json(
                ['message' => $exception->getMessage(), 'success' => 'false'],
                $exception instanceof ShopwareHttpException ? $exception->getStatusCode() : 500
            );
        }

        return new JsonResponse([
            'success' => true,
        ]);
    }
}
