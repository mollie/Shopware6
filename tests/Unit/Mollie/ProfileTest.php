<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Profile;
use PHPUnit\Framework\TestCase;

#[CoversClass(Profile::class)]
final class ProfileTest extends TestCase
{
    public function testFromClientResponse(): void
    {
        $body = [
            'id' => '123',
            'name' => 'Max',
            'email' => 'Max.Mollie@test.de'
        ];

        $profile = Profile::fromClientResponse($body);

        $this->assertEquals('123', $profile->getId());
        $this->assertEquals('Max', $profile->getName());
        $this->assertEquals('Max.Mollie@test.de', $profile->getEmail());
    }
}
