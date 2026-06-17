<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Controller;

use Mollie\Shopware\Component\Payment\PaymentMethodInstaller;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
final class PaymentMethodController extends AbstractController
{
    public function __construct(
        private PaymentMethodInstaller $paymentMethodInstaller,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/api/_action/mollie/payment-method/update-methods', name: 'api.mollie.payment-method.update-methods', methods: ['GET'])]
    public function update(Context $context): JsonResponse
    {
        try {
            $this->paymentMethodInstaller->install($context);
            $this->logger->info('Update payment methods action executed from Plugin Configuration');

            return new JsonResponse([
                'success' => true,
            ]);
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
            $statusCode = $exception instanceof ShopwareHttpException ? $exception->getStatusCode() : 500;
            $this->logger->error('Failed to update payment methods action ', [
                'error' => $message,
            ]);

            return new JsonResponse([
                'success' => false,
                'message' => $message,
            ], $statusCode);
        }
    }
}
