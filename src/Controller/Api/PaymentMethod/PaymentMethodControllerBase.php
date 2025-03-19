<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\PaymentMethod;

use Kiener\MolliePayments\Service\PaymentMethodService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

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

    public function __construct(PaymentMethodService $paymentMethodService, LoggerInterface $logger)
    {
        $this->paymentMethodService = $paymentMethodService;
        $this->logger = $logger;
    }

    public function updatePaymentMethods(Context $context): JsonResponse
    {
        return $this->updatePaymentMethodsAction($context);
    }

    public function updatePaymentMethodsLegacy(Context $context): JsonResponse
    {
        return $this->updatePaymentMethodsAction($context);
    }

    private function updatePaymentMethodsAction(Context $context): JsonResponse
    {
        try {
            $this->paymentMethodService->installAndActivatePaymentMethods($context);

            return $this->json([
                'success' => true,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage());

            return $this->json(
                ['message' => $exception->getMessage(), 'success' => 'false'],
                $exception instanceof ShopwareHttpException ? $exception->getStatusCode() : 500
            );
        }
    }
}
