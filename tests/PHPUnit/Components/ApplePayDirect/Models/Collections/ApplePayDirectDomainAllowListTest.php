<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Components\ApplePayDirect\Models\Collections;

use Kiener\MolliePayments\Components\ApplePayDirect\Models\ApplePayDirectDomainAllowListItem;
use Kiener\MolliePayments\Components\ApplePayDirect\Models\Collections\ApplePayDirectDomainAllowList;
use PHPUnit\Framework\TestCase;

class ApplePayDirectDomainAllowListTest extends TestCase
{
    public function testCanDetermineIfValueIsContainedInList(): void
    {
        $allowList = ApplePayDirectDomainAllowList::create(
            ApplePayDirectDomainAllowListItem::create('example.com'),
            ApplePayDirectDomainAllowListItem::create('example-url.org')
        );

        $this->assertTrue($allowList->contains('example.com'));
        $this->assertTrue($allowList->contains('example-url.org'));
        $this->assertFalse($allowList->contains('example-url.net'));
    }

    public function testCanDetermineIfListIsEmpty(): void
    {
        $allowList = ApplePayDirectDomainAllowList::create();

        $this->assertTrue($allowList->isEmpty());
    }

    public function testProvidesCount(): void
    {
        $allowList = ApplePayDirectDomainAllowList::create(
            ApplePayDirectDomainAllowListItem::create('example.com'),
            ApplePayDirectDomainAllowListItem::create('example-url.org')
        );

        $this->assertCount(2, $allowList);
    }
}
