<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\FailureMode\Fake;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\AccountOrderController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stands in for Shopware's own account order controller, which the plugin decorates. Every method
 * answers with a recognizable response so a test can tell "handed through" from "handled here".
 */
final class FakeAccountOrderController extends AccountOrderController
{
    /** @var list<string> */
    private array $calls = [];

    public function __construct()
    {
    }

    public function orderOverview(Request $request, SalesChannelContext $context): Response
    {
        return $this->record('orderOverview');
    }

    public function cancelOrder(Request $request, SalesChannelContext $context): Response
    {
        return $this->record('cancelOrder');
    }

    public function orderSingleOverview(Request $request, SalesChannelContext $context): Response
    {
        return $this->record('orderSingleOverview');
    }

    public function ajaxOrderDetail(Request $request, SalesChannelContext $context): Response
    {
        return $this->record('ajaxOrderDetail');
    }

    public function orderChangePayment(string $orderId, Request $request, SalesChannelContext $context): Response
    {
        return $this->record('orderChangePayment');
    }

    public function updateOrder(string $orderId, Request $request, SalesChannelContext $context): Response
    {
        return $this->record('updateOrder');
    }

    public function editOrder(string $orderId, Request $request, SalesChannelContext $context): Response
    {
        return $this->record('editOrder');
    }

    /**
     * @return list<string>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    private function record(string $method): Response
    {
        $this->calls[] = $method;

        return new Response($method);
    }
}
