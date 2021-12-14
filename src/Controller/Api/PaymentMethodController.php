<?php

namespace Kiener\MolliePayments\Controller\Api;

use Exception;
use Kiener\MolliePayments\Service\PaymentMethodService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class PaymentMethodController extends AbstractController
{
    /**
     * @var PaymentMethodService
     */
    private $paymentMethodService;


    /**
     * @param PaymentMethodService $paymentMethodService
     */
    public function __construct(PaymentMethodService $paymentMethodService)
    {
        $this->paymentMethodService = $paymentMethodService;
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
        $success = true;

        try {
            $this->paymentMethodService->installAndActivatePaymentMethods($context);
        } catch (Exception $e) {
            $success = false;
        }

        return new JsonResponse([
            'success' => $success,
        ]);
    }
}
