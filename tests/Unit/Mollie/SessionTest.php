<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\LineItemType;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\Session;
use Mollie\Shopware\Component\Mollie\SessionStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Session::class)]
final class SessionTest extends TestCase
{
    public function testReadsStatusMethodAndToken(): void
    {
        $session = Session::createFromClientResponse($this->completedSessionBody());

        $this->assertSame('sess_tkF7ATr7c3bx7dBWaNdVJ', $session->getId());
        $this->assertSame(SessionStatus::COMPLETED, $session->getStatus());
        $this->assertTrue($session->getStatus()->isCompleted());
        $this->assertSame(PaymentMethod::CREDIT_CARD, $session->getMethod());
        $this->assertSame('tkn_pyEQ5ABHvE', $session->getCardToken());
        $this->assertSame('googlepay', $session->getWallet());
        $this->assertSame('redirect', $session->getNextAction());
    }

    public function testDerivesThePaymentIdFromTheReturnLink(): void
    {
        $session = Session::createFromClientResponse($this->completedSessionBody());

        $this->assertSame('tr_TTU7UryAa6hRu8dJeNdVJ', $session->getPaymentId());
    }

    public function testPaymentIdStaysEmptyWithoutReturnLink(): void
    {
        $body = $this->completedSessionBody();
        unset($body['_links']);

        $session = Session::createFromClientResponse($body);

        $this->assertSame('', $session->getPaymentId());
    }

    public function testResolvesTheSelectedShippingOption(): void
    {
        $session = Session::createFromClientResponse($this->completedSessionBody());

        $shippingLine = $session->getSelectedShippingLine();
        $this->assertNotNull($shippingLine);
        $this->assertSame(LineItemType::SHIPPING, $shippingLine->getType());

        $shippingOption = $session->getSelectedShippingOption();
        $this->assertNotNull($shippingOption);
        $this->assertSame('18a749392801df0f024c86fbac59b2b4', $shippingOption->getReference());
    }

    /**
     * The options moved into a shipping sub object on the request, so a response may carry
     * them there too. Reading only the root level would leave the collection empty and the
     * express checkout would fall back to the shipping method of the context.
     */
    public function testResolvesTheSelectedShippingOptionFromTheShippingSubObject(): void
    {
        $body = $this->completedSessionBody();
        $body['shipping'] = ['options' => $body['shippingOptions']];
        unset($body['shippingOptions']);

        $session = Session::createFromClientResponse($body);

        $this->assertCount(2, $session->getShippingOptions());
        $this->assertSame('18a749392801df0f024c86fbac59b2b4', $session->getSelectedShippingOption()?->getReference());
    }

    /**
     * During the rollout a response can carry both shapes; the nested one is the current
     * contract and has to win.
     */
    public function testTheNestedShippingOptionsWinOverTheRootLevelOnes(): void
    {
        $body = $this->completedSessionBody();
        $body['shipping'] = ['options' => [[
            'reference' => 'nested-shipping-method-id',
            'description' => 'Mollie Cheap Test Shipment',
            'amount' => ['value' => '0.01', 'currency' => 'EUR'],
        ]]];

        $session = Session::createFromClientResponse($body);

        $this->assertCount(1, $session->getShippingOptions());
        $this->assertSame('nested-shipping-method-id', $session->getSelectedShippingOption()?->getReference());
    }

    public function testNoShippingOptionWithoutAShippingLine(): void
    {
        $body = $this->completedSessionBody();
        $body['lines'] = [$body['lines'][0]];

        $session = Session::createFromClientResponse($body);

        $this->assertNull($session->getSelectedShippingLine());
        $this->assertNull($session->getSelectedShippingOption());
    }

    public function testNoShippingOptionWhenTheAmountDiffers(): void
    {
        $body = $this->completedSessionBody();
        $body['lines'][1]['totalAmount'] = ['value' => '2.99', 'currency' => 'EUR'];

        $session = Session::createFromClientResponse($body);

        $this->assertNull($session->getSelectedShippingOption());
    }

    /**
     * Mollie leaves fields it has no value for out of the response instead of sending them as
     * null, which used to trigger an "Undefined array key streetAdditional" warning.
     */
    public function testBillingAddressWithoutOptionalFields(): void
    {
        $session = Session::createFromClientResponse($this->completedSessionBody());

        $billingAddress = $session->getBillingAddress();
        $this->assertNotNull($billingAddress);
        $this->assertSame('max@example.com', $billingAddress->getEmail());
        $this->assertSame('Northeim', $billingAddress->getCity());

        // the shipping address of an express session carries no email, it is taken from billing
        $shippingAddress = $session->getShippingAddress();
        $this->assertNotNull($shippingAddress);
        $this->assertSame('max@example.com', $shippingAddress->getEmail());
    }

    public function testDefaultsForAnOpenSession(): void
    {
        $session = Session::createFromClientResponse(['id' => 'sess_open']);

        $this->assertSame(SessionStatus::OPEN, $session->getStatus());
        $this->assertNull($session->getMethod());
        $this->assertNull($session->getCardToken());
        $this->assertSame('', $session->getClientAccessToken());
        $this->assertCount(0, $session->getLines());
        $this->assertCount(0, $session->getShippingOptions());
    }

    /**
     * @return array<string, mixed>
     */
    private function completedSessionBody(): array
    {
        return [
            'id' => 'sess_tkF7ATr7c3bx7dBWaNdVJ',
            'clientAccessToken' => 'token',
            'status' => 'completed',
            'nextAction' => 'redirect',
            'amount' => ['currency' => 'EUR', 'value' => '0.02'],
            'method' => 'creditcard',
            'methodDetails' => [
                'method' => 'creditcard',
                'params' => [
                    'creditcard' => [
                        'pspToken' => 'tkn_pyEQ5ABHvE',
                        'wallet' => 'googlepay',
                    ],
                ],
            ],
            'shippingAddress' => [
                'givenName' => 'Max',
                'familyName' => 'Mustermann',
                'streetAndNumber' => 'Teststreet 1',
                'postalCode' => '37154',
                'city' => 'Northeim',
                'country' => 'DE',
            ],
            'billingAddress' => [
                'givenName' => 'Max',
                'familyName' => 'Mustermann',
                'email' => 'max@example.com',
                'streetAndNumber' => 'Teststreet 1',
                'postalCode' => '37154',
                'city' => 'Northeim',
                'country' => 'DE',
            ],
            'shippingOptions' => [
                [
                    'reference' => '019fd687af2a71c2a8d6662eb1d1c919',
                    'description' => 'Standard',
                    'amount' => ['value' => '0.00', 'currency' => 'EUR'],
                ],
                [
                    'reference' => '18a749392801df0f024c86fbac59b2b4',
                    'description' => 'Mollie Cheap Test Shipment',
                    'amount' => ['value' => '0.01', 'currency' => 'EUR'],
                ],
            ],
            'lines' => [
                [
                    'sku' => 'MOL_ONE_CENT',
                    'type' => 'physical',
                    'quantity' => 1,
                    'description' => 'One Cent Mollie Shirt',
                    'unitPrice' => ['value' => '0.01', 'currency' => 'EUR'],
                    'totalAmount' => ['value' => '0.01', 'currency' => 'EUR'],
                ],
                [
                    'type' => 'shipping_fee',
                    'quantity' => 1,
                    'description' => 'Mollie Cheap Test Shipment',
                    'unitPrice' => ['value' => '0.01', 'currency' => 'EUR'],
                    'totalAmount' => ['value' => '0.01', 'currency' => 'EUR'],
                ],
            ],
            '_links' => [
                'redirect' => ['href' => 'https://mollie.com/checkout/return/TTU7UryAa6hRu8dJeNdVJ'],
            ],
        ];
    }
}
