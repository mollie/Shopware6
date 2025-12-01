<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Route;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

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

    public function return(Request $request, Context $context): ReturnRouteResponse
    {
        $transactionId = $request->get('transactionId');
        $payment = $this->mollieGateway->getPaymentByTransactionId($transactionId, $context);
        $this->logger->debug('Return route opened');

        return new ReturnRouteResponse($payment);
    }
}
