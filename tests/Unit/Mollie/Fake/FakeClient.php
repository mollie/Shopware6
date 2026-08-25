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

    /** @var list<string> */
    private array $requestedUris = [];

    /** @var list<string> */
    private array $requestedMethods = [];

    /**
     * @var array<string, mixed>
     */
    private array $lastGetOptions = [];

    /**
     * @var array<string, mixed>
     */
    private array $lastPostOptions = [];

    /**
     * @var array<string, mixed>
     */
    private array $lastPatchOptions = [];

    /**
     * @var array<string, mixed>
     */
    private array $lastDeleteOptions = [];

    public function __construct(private ?string $id = null,
        private ?string $status = 'failed',
        private ?PaymentMethod $method = PaymentMethod::PAYPAL,
        private ?bool $embed = false,
        private ?string $checkoutUrl = null,
        private ?array $amountCaptured = null,
        private ?array $amount = null,
        private ?array $amountRefunded = null,
        ?array $body = null,
    ) {
        if ($body !== null) {
            $this->response = new Response(body: (string) json_encode($body));

            return;
        }
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
        $this->requestedUris[] = (string) $uri;
        $this->requestedMethods[] = 'GET';
        $this->lastGetOptions = $options;
        if ($this->response->getStatusCode() === 500) {
            $request = new Request('GET', $uri);
            throw new ClientException('Exception was triggered', $request, $this->response);
        }

        return $this->response;
    }

    public function post($uri, array $options = []): ResponseInterface
    {
        $this->requestedUris[] = (string) $uri;
        $this->requestedMethods[] = 'POST';
        $this->lastPostOptions = $options;
        if ($this->response->getStatusCode() === 500) {
            $request = new Request('POST', $uri);
            throw new ClientException('Exception was triggered', $request, $this->response);
        }

        return $this->response;
    }

    public function patch($uri, array $options = []): ResponseInterface
    {
        $this->requestedUris[] = (string) $uri;
        $this->requestedMethods[] = 'PATCH';
        $this->lastPatchOptions = $options;
        if ($this->response->getStatusCode() === 500) {
            $request = new Request('PATCH', $uri);
            throw new ClientException('Exception was triggered', $request, $this->response);
        }

        return $this->response;
    }

    public function delete($uri, array $options = []): ResponseInterface
    {
        $this->requestedUris[] = (string) $uri;
        $this->requestedMethods[] = 'DELETE';
        $this->lastDeleteOptions = $options;
        if ($this->response->getStatusCode() === 500) {
            $request = new Request('DELETE', $uri);

            throw new ClientException('Exception was triggered', $request, $this->response);
        }

        return $this->response;
    }

    public function getLastMethod(): string
    {
        $lastMethod = end($this->requestedMethods);

        return $lastMethod === false ? '' : $lastMethod;
    }

    public function getLastUri(): string
    {
        $lastUri = end($this->requestedUris);

        return $lastUri === false ? '' : $lastUri;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLastGetOptions(): array
    {
        return $this->lastGetOptions;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLastPostOptions(): array
    {
        return $this->lastPostOptions;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLastPatchOptions(): array
    {
        return $this->lastPatchOptions;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLastDeleteOptions(): array
    {
        return $this->lastDeleteOptions;
    }
}
