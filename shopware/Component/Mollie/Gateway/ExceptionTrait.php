<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use GuzzleHttp\Exception\ClientException;
use Mollie\Shopware\Component\Mollie\Exception\ApiException;

trait ExceptionTrait
{
    private function convertException(ClientException $exception, ?string $orderNumber = null): ApiException
    {
        $body = json_decode($exception->getResponse()->getBody()->getContents(), true);
        $logData = [
            'title' => $body['title'] ?? 'no title',
            'error' => $body['detail'] ?? 'no details',
            'field' => $body['field'] ?? 'no field',
        ];
        if ($orderNumber !== null) {
            $logData['orderNumber'] = $orderNumber;
        }
        $this->logger->error('There was an error from Mollies API', $logData);

        return new ApiException($exception->getCode(), $body['title'] ?? '', $body['detail'] ?? '', $body['field'] ?? '');
    }
}
