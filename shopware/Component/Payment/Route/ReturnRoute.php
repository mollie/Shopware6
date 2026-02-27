<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Route;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => false, 'auth_enabled' => false])]
final class ReturnRoute extends AbstractReturnRoute
{
    public function __construct(
        #[Autowire(service: MollieGateway::class)]
        private MollieGatewayInterface $mollieGateway,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): AbstractReturnRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/api/mollie/payment/return/{transactionId}',name: 'api.mollie.payment-return', methods: ['GET', 'POST'])]
    public function return(string $transactionId, Context $context): ReturnRouteResponse
    {
        $this->logger->debug('Return route opened',[
            'transactionId' => $transactionId,
        ]);
        $payment = $this->mollieGateway->getPaymentByTransactionId($transactionId, $context);

        return new ReturnRouteResponse($payment);
    }
}
