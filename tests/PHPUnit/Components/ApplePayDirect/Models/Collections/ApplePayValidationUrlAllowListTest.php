<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Components\ApplePayDirect\Models\Collections;


use Kiener\MolliePayments\Components\ApplePayDirect\Models\ApplePayValidationUrlAllowListItem;
use Kiener\MolliePayments\Components\ApplePayDirect\Models\Collections\ApplePayValidationUrlAllowList;
use PHPUnit\Framework\TestCase;

class ApplePayValidationUrlAllowListTest extends TestCase
{
    public function canDetermineIfValueIsContainedInList(): void
    {
        $allowList = ApplePayValidationUrlAllowList::create(
            ApplePayValidationUrlAllowListItem::create('https://example.com'),
            ApplePayValidationUrlAllowListItem::create('https://example-url.org')
        );

        $this->assertTrue($allowList->contains('https://example.com'));
        $this->assertTrue($allowList->contains('https://example-url.org'));
        $this->assertFalse($allowList->contains('https://example-url.net'));
    }

    public function canDetermineIfListIsEmpty(): void
    {
        $allowList = ApplePayValidationUrlAllowList::create();

        $this->assertTrue($allowList->isEmpty());
    }

    public function testProvidesCount(): void
    {
        $allowList = ApplePayValidationUrlAllowList::create(
            ApplePayValidationUrlAllowListItem::create('https://example.com'),
            ApplePayValidationUrlAllowListItem::create('https://example-url.org')
        );

        $this->assertCount(2, $allowList);
    }
}