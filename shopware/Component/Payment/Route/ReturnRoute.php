<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Route;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class ReturnRoute extends AbstractReturnRoute
{
    public function __construct(
        private MollieGatewayInterface $mollieGateway,
        private LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): AbstractReturnRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public function return(string $transactionId, SalesChannelContext $context): ReturnRouteResponse
    {
        $payment = $this->mollieGateway->getPaymentByTransactionId($transactionId, $context->getContext());

        return new ReturnRouteResponse($payment);
    }
}
