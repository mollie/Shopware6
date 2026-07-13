<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Fake;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Psr\Http\Message\ResponseInterface;

final class FakeClient extends Client
{
    private ResponseInterface $response;

    /**
     * @var array<string, mixed>
     */
    private array $lastPostOptions = [];

    public function __construct(private ?string $id = null,
        private ?string $status = 'failed',
        private ?PaymentMethod $method = PaymentMethod::PAYPAL,
        private ?bool $embed = false,
        private ?string $checkoutUrl = null,
        private ?array $amountCaptured = null,
        private ?array $amount = null,
        private ?array $amountRefunded = null,
    ) {
        if ($id === null) {
            $this->response = new Response(status: 500, body: json_encode([
                'title' => 'Failed Response',
                'detail' => 'This response failed and simulate an exception',
                'field' => 'payment.id',
            ]));

            return;
        }
        $body = ['id' => $id, 'status' => $status];
        if ($method !== null) {
            $body['method'] = $method->value;
        }
        if ($this->checkoutUrl !== null) {
            $body['_links']['checkout']['href'] = $this->checkoutUrl;
        }
        if ($this->amountCaptured !== null) {
            $body['amountCaptured'] = $this->amountCaptured;
        }
        if ($this->amount !== null) {
            $body['amount'] = $this->amount;
        }
        if ($this->amountRefunded !== null) {
            $body['amountRefunded'] = $this->amountRefunded;
        }
        if ($embed) {
            $body['_embedded']['payments'][0] = $body;
        }
        $this->response = new Response(body: json_encode($body));
    }

    public function get($uri, array $options = []): ResponseInterface
    {
        if ($this->response->getStatusCode() === 500) {
            $request = new Request('GET', $uri);
            throw new ClientException('Exception was triggered', $request, $this->response);
        }

        return $this->response;
    }

    public function post($uri, array $options = []): ResponseInterface
    {
        $this->lastPostOptions = $options;
        if ($this->response->getStatusCode() === 500) {
            $request = new Request('POST', $uri);
            throw new ClientException('Exception was triggered', $request, $this->response);
        }

        return $this->response;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLastPostOptions(): array
    {
        return $this->lastPostOptions;
    }
}
