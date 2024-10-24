<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\Order;

use Kiener\MolliePayments\Components\CancelManager\CancelItemFacade;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\OrderLine;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CancelLineController extends AbstractController
{
    private MollieApiFactory $clientFactory;
    private CancelItemFacade $cancelItemFacade;

    public function __construct(MollieApiFactory $clientFactory, CancelItemFacade $cancelItemFacade)
    {
        $this->clientFactory = $clientFactory;
        $this->cancelItemFacade = $cancelItemFacade;
    }

    public function statusAction(Request $request, Context $context): Response
    {
        $orderId = $request->get('mollieOrderId');
        $result = [];
        $client = $this->clientFactory->getClient();
        try {
            $mollieOrder = $client->orders->get($orderId);
        } catch (ApiException $e) {
            return new JsonResponse($result);
        }

        $lines = $mollieOrder->lines();
        if ($lines->count() > 0) {
            /** @var OrderLine $line */
            foreach ($lines as $line) {
                $metadata = $line->metadata;
                if (!is_object($metadata) || ! property_exists($metadata, 'orderLineItemId')) {
                    continue;
                }
                $id = $metadata->orderLineItemId;

                $result[$id] = [
                    'mollieOrderId' => $orderId,
                    'mollieId' => $line->id,
                    'status' => $line->status,
                    'isCancelable' => $line->isCancelable,
                    'cancelableQuantity' => $line->cancelableQuantity,
                    'quantityCanceled' => $line->quantityCanceled
                ];
            }
        }

        return new JsonResponse($result);
    }

    public function cancelAction(Request $request, Context $context): Response
    {
        $mollieOrderId = $request->get('mollieOrderId');
        $mollieLineId = $request->get('mollieLineId');
        $quantity = $request->get('canceledQuantity');
        $shopwareOrderLineId = $request->get('shopwareLineId');
        $resetStock = $request->get('resetStock', false);

        $result = $this->cancelItemFacade->cancelItem($mollieOrderId, $mollieLineId, $shopwareOrderLineId, $quantity, $resetStock, $context);
        return new JsonResponse($result->toArray());
    }
}
