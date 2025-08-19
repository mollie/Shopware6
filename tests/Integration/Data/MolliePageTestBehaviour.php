<?php
declare(strict_types=1);

namespace Mollie\Integration\Data;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

trait MolliePageTestBehaviour
{
    public function selectMolliePaymentStatus(string $paymentStatus, string $url): void
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

        $response = $client->post($formLocation, [
            RequestOptions::FORM_PARAMS => $formData,
            RequestOptions::ALLOW_REDIRECTS => false,
        ]);
    }
}
