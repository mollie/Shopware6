<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\Controller;

use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\HttpFoundation\Request;

class RequestBagFactory
{
    public function createForShipping(Request $request): RequestDataBag
    {
        $result = new RequestDataBag();
        $result->set('orderId', $request->get('orderId'));

        return $result;
    }
}
