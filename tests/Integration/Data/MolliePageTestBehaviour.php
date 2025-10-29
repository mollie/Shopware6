<?php
declare(strict_types=1);

namespace Mollie\Integration\Data;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

trait MolliePageTestBehaviour
{
    public function fillCreditCardData(string $url, string $cardNumber, string $cardHolder, string $expireDate, string $ccv): ResponseInterface
    {
        $client = new Client();
        $response = $client->get($url, [RequestOptions::ALLOW_REDIRECTS => false]);
        $formLocation = $response->getHeader('location')[0];

        $htmlContent = file_get_contents($formLocation);

        $dom = new \DOMDocument();
        try {
            $dom = $dom->loadHTML($htmlContent);
        } catch (\Throwable $exception) {
        }
        $form = $dom->getElementById('body');
        $inputs = $form->getElementsByTagName('input');

        $formData = [
            'submit-button' => ''
        ];
        foreach ($inputs as $input) {
            $inputName = $input->getAttribute('name');
            $inputValue = $input->getAttribute('value');
            $inputType = $input->getAttribute('type');

            dump($inputName, $inputType);
            if (isset($formData[$inputName])) {
                continue;
            }
            $formData[$inputName] = $inputValue;
        }
        dump($formData);

        return $client->post($formLocation, [
            RequestOptions::FORM_PARAMS => $formData,
            RequestOptions::ALLOW_REDIRECTS => false,
        ]);
    }

    public function selectMolliePaymentStatus(string $paymentStatus, string $url): ResponseInterface
    {
        $client = new Client();
        $response = $client->get($url, [RequestOptions::ALLOW_REDIRECTS => false]);

        $formLocation = $response->getHeader('location')[0] ?? null;
        if ($formLocation === null) {
            $htmlContent = $response->getBody()->getContents();
            $formLocation = $url;
        } else {
            $htmlContent = file_get_contents($formLocation);
        }

        $dom = new \DOMDocument();
        try {
            $dom = $dom->loadHTML($htmlContent);
        } catch (\Throwable $exception) {
        }

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

        return $client->post($formLocation, [
            RequestOptions::FORM_PARAMS => $formData,
            RequestOptions::ALLOW_REDIRECTS => false,
        ]);
    }
}
