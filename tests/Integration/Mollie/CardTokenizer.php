<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Mollie;

use GuzzleHttp\Client;
use PHPUnit\Framework\Assert;

/**
 * Exchanges a Mollie test card for the card token a credit card payment needs.
 *
 * NEVER USE THIS IN A LIVE SHOP, AND NEVER MOVE IT INTO shopware/. Handing raw card data to the
 * shop is forbidden - that is why mollie.js collects it in an iframe and only ever gives the shop a
 * token. This class exists because Behat has no browser to run mollie.js in, and it only ever sees
 * Mollie's published test card numbers. It lives in tests/ on purpose: the plugin does not load it,
 * so no merchant installation can reach it.
 *
 * The endpoint is the one mollie.js calls, not the regular API: it takes no API key - the profile id
 * is the credential - and it wants the headers of the component that would normally send it. The
 * "tokenisation agent" is no secret, only base64 encoded JSON describing the caller.
 *
 * @see https://docs.mollie.com/docs/testing
 */
final class CardTokenizer
{
    private const CARD_TOKEN_URL = 'https://api.cc.mollie.com/v1/card-tokens';
    private const COMPONENTS_ORIGIN = 'https://js.mollie.com';

    /**
     * Holder, CVV and expiry date are free to choose for a test card, it only has to be valid.
     */
    private const CARD_HOLDER = 'Test Persona';
    private const CARD_CVV = '123';

    private const CARD_NUMBERS = [
        'American Express' => '378282246310005',
        'Mastercard' => '2223000010479399',
        'VISA' => '4543474002249996',
    ];

    public function createCardToken(string $cardBrand, string $profileId, bool $testMode, string $hostname): string
    {
        Assert::assertArrayHasKey($cardBrand, self::CARD_NUMBERS, sprintf('Mollie has no test card for "%s"', $cardBrand));

        $client = new Client();
        $response = $client->post(self::CARD_TOKEN_URL, [
            'headers' => [
                'Origin' => self::COMPONENTS_ORIGIN,
                'Referer' => self::COMPONENTS_ORIGIN . '/',
                'Tokenisation-Agent' => $this->buildTokenisationAgent($hostname),
            ],
            'json' => [
                'cardHolder' => self::CARD_HOLDER,
                'cardNumber' => self::CARD_NUMBERS[$cardBrand],
                'cardCvv' => self::CARD_CVV,
                'cardExpiryDate' => (new \DateTime('+2 years'))->format('m/y'),
                'testmode' => $testMode,
                'profileToken' => $profileId,
                'hostname' => $hostname,
            ],
        ]);

        $body = json_decode($response->getBody()->getContents(), true);

        Assert::assertIsArray($body, 'Mollie answered the tokenisation with something other than JSON');
        Assert::assertArrayHasKey('cardToken', $body, 'Mollie answered the tokenisation without a card token');

        return (string) $body['cardToken'];
    }

    private function buildTokenisationAgent(string $hostname): string
    {
        return base64_encode((string) json_encode([
            'product' => 'Components1.0',
            'productVersion' => null,
            'productLocation' => 'card',
            'parentUri' => $hostname,
            'sourceUri' => null,
            'plugin' => null,
            'pluginVersion' => null,
        ]));
    }
}
