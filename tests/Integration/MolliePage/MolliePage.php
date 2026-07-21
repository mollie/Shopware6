<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\MolliePage;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class MolliePage
{
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_MS = 500;

    private Client $client;

    public function __construct(private readonly string $url)
    {
        $this->client = $this->createClient();
    }

    public function selectIssuer(string $issuer): ResponseInterface
    {
        $page = $this->loadPage($this->url);
        $dom = $page->getDom();

        $form = $dom->getElementById('body');
        $inputs = $form->getElementsByTagName('input');

        $formData = [
            'issuer' => $issuer,
        ];

        foreach ($inputs as $input) {
            $inputName = $input->getAttribute('name');
            $inputValue = $input->getAttribute('value');
            if (isset($formData[$inputName])) {
                continue;
            }
            $formData[$inputName] = $inputValue;
        }

        return $this->submit($page->getUrl(), $formData);
    }

    public function selectPaymentStatus(string $paymentStatus): ResponseInterface
    {
        $page = $this->loadPage($this->url);
        $dom = $page->getDom();
        $form = $dom->getElementById('body');

        $inputs = $form->getElementsByTagName('input');

        $formData = [
            'submit' => ''
        ];

        foreach ($inputs as $input) {
            $inputName = $input->getAttribute('name');
            $inputValue = $input->getAttribute('value');
            $inputType = $input->getAttribute('type');

            if ($inputType === 'radio' && $inputValue === $paymentStatus) {
                $formData[$inputName] = $inputValue;
                continue;
            }
            if (isset($formData[$inputName])) {
                continue;
            }
            $formData[$inputName] = $inputValue;
        }

        Assert::assertTrue(isset($formData['final_state']));
        Assert::assertEquals($paymentStatus, $formData['final_state']);

        return $this->submit($page->getUrl(), $formData);
    }

    public function selectPaymentMethod(string $molliePaymentMethod): ResponseInterface
    {
        $page = $this->loadPage($this->url);
        $dom = $page->getDom();

        $form = $dom->getElementById('body');
        $url = $form->getAttribute('action');
        $inputs = $form->getElementsByTagName('input');

        $formData = [
            'method' => $molliePaymentMethod,
        ];

        foreach ($inputs as $input) {
            $inputName = $input->getAttribute('name');
            $inputValue = $input->getAttribute('value');
            if (isset($formData[$inputName])) {
                continue;
            }
            $formData[$inputName] = $inputValue;
        }

        return $this->submit($url, $formData);
    }

    public function getShopwareReturnPage(): string
    {
        $response = $this->client->get($this->url, [
            RequestOptions::ALLOW_REDIRECTS => false,
        ]);

        return $response->getHeaderLine('location');
    }

    private function loadPage(string $url): PageResponse
    {
        $response = $this->client->get($url, [RequestOptions::ALLOW_REDIRECTS => false]);

        $formLocation = $response->getHeader('location')[0] ?? null;
        if ($formLocation === null) {
            $htmlContent = $response->getBody()->getContents();
            $formLocation = $url;
        } else {
            $htmlContent = $this->client->get($formLocation)->getBody()->getContents();
        }

        $dom = new \DOMDocument();
        try {
            $dom = $dom->loadHTML($htmlContent);
        } catch (\Throwable $exception) {
        }

        return new PageResponse($formLocation, $dom);
    }

    private function submit(string $url, array $formData): ResponseInterface
    {
        return $this->client->post($url, [
            RequestOptions::FORM_PARAMS => $formData,
            RequestOptions::ALLOW_REDIRECTS => false,
        ]);
    }

    /**
     * The Mollie test-mode checkout occasionally resets the connection (cURL error 35),
     * which makes the Behat suite flaky. Retry connection failures and 5xx responses
     * with a small linear backoff.
     */
    private function createClient(): Client
    {
        $decider = function (int $retries, RequestInterface $request, ?ResponseInterface $response = null, ?\Throwable $exception = null): bool {
            if ($retries >= self::MAX_RETRIES) {
                return false;
            }
            if ($exception instanceof ConnectException) {
                return true;
            }

            return $response !== null && $response->getStatusCode() >= 500;
        };

        $delay = function (int $retries): int {
            return self::RETRY_DELAY_MS * $retries;
        };

        $stack = HandlerStack::create();
        $stack->push(Middleware::retry($decider, $delay));

        return new Client(['handler' => $stack]);
    }
}
