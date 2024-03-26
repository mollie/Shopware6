<?php

namespace Kiener\MolliePayments\Controller\Api\PaymentMethod;

use Kiener\MolliePayments\Service\PaymentMethodService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

class PaymentMethodControllerBase extends AbstractController
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
     *
     * @param Context $context
     * @return JsonResponse
     */
    public function updatePaymentMethods(Context $context): JsonResponse
    {
        return $this->updatePaymentMethodsAction($context);
    }

    /**
     *
     * @param Context $context
     * @return JsonResponse
     */
    public function updatePaymentMethodsLegacy(Context $context): JsonResponse
    {
        return $this->updatePaymentMethodsAction($context);
    }

    /**
     * @param Context $context
     * @return JsonResponse
     */
    private function updatePaymentMethodsAction(Context $context): JsonResponse
    {
        try {
            $this->paymentMethodService->installAndActivatePaymentMethods($context);

            return $this->json([
                'success' => true,
            ]);
        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage());

            return $this->json(
                ['message' => $exception->getMessage(), 'success' => 'false'],
                $exception instanceof ShopwareHttpException ? $exception->getStatusCode() : 500
            );
        }
    }
}
