<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Route;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

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

    public function return(Request $request, SalesChannelContext $context): ReturnRouteResponse
    {
        $transactionId = $request->get('transactionId');
        $payment = $this->mollieGateway->getPaymentByTransactionId($transactionId, $context->getContext());

        return new ReturnRouteResponse($payment);
    }
}
