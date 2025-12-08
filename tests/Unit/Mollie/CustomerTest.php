<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Customer;
use Mollie\Shopware\Component\Mollie\Locale;
use PHPUnit\Framework\TestCase;

#[CoversClass(Customer::class)]
final class CustomerTest extends TestCase
{
    public function testFromClientResponseWithLocale(): void
    {
        $body = $this->getClientResponseBody('de_DE');
        $customer = Customer::fromClientResponse($body);

        $this->assertSame('123', $customer->getId());
        $this->assertSame('Max', $customer->getName());
        $this->assertSame('Max.Mollie@test.com', $customer->getEmail());
        $this->assertSame(['key' => 'value'], $customer->getMetaData());
        $this->assertSame(Locale::deDE, $customer->getLocale());
    }

    public function testSetAndGetLocale(): void
    {
        $customer = new Customer('123', 'Max', 'Max.Mollie@test.com', []);
        $customer->setLocale(Locale::deDE);

        $this->assertSame(Locale::deDE, $customer->getLocale());
    }

    public function testJsonSerialize(): void
    {
        $customer = new Customer('123', 'Max', 'Max.Mollie@test.de', ['ref' => 'abc']);

        $json = $customer->jsonSerialize();

        $this->assertIsArray($json);
        $this->assertSame('123', $json['id']);
        $this->assertSame('Max', $json['name']);
        $this->assertSame('Max.Mollie@test.de', $json['email']);
        $this->assertSame(['ref' => 'abc'], $json['metaData']);
    }

    /**
     * @return array<mixed>
     */
    private function getClientResponseBody(?string $locale): array
    {
        return [
            'id' => '123',
            'name' => 'Max',
            'email' => 'Max.Mollie@test.com',
            'metadata' => ['key' => 'value'],
            'locale' => $locale,
        ];
    }
}
