<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\SalesChannel;

use Mollie\Shopware\Component\SalesChannel\LocaleProvider;
use Mollie\Shopware\Unit\Fake\FakeLanguageRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

/**
 * The locale decides which language Mollie shows the customer in the hosted checkout, so a wrong
 * or missing one is visible to the shopper.
 */
#[CoversClass(LocaleProvider::class)]
final class LocaleProviderTest extends TestCase
{
    public function testTheLocaleOfTheOrdersLanguageIsUsed(): void
    {
        $provider = new LocaleProvider(new FakeLanguageRepository('de-DE'));

        $localeCode = $provider->getLocaleCode(Context::createDefaultContext()->getLanguageId(), Context::createDefaultContext());

        $this->assertSame('de-DE', $localeCode);
    }

    /**
     * A language that is gone or has no locale must not stop the checkout, so Mollie gets en-GB.
     */
    public function testAnUnknownLanguageFallsBackToEnglish(): void
    {
        $provider = new LocaleProvider(new FakeLanguageRepository());

        $localeCode = $provider->getLocaleCode('language-that-does-not-exist', Context::createDefaultContext());

        $this->assertSame('en-GB', $localeCode);
    }

    public function testTheSameLanguageIsLookedUpOnlyOnce(): void
    {
        $repository = new FakeLanguageRepository('de-DE');
        $provider = new LocaleProvider($repository);
        $languageId = Context::createDefaultContext()->getLanguageId();

        $provider->getLocaleCode($languageId, Context::createDefaultContext());
        $provider->getLocaleCode($languageId, Context::createDefaultContext());

        $this->assertSame([$languageId], $repository->getRequestedIds());
    }

    public function testADifferentLanguageIsLookedUpOnItsOwn(): void
    {
        $repository = new FakeLanguageRepository('de-DE');
        $provider = new LocaleProvider($repository);
        $languageId = Context::createDefaultContext()->getLanguageId();

        $provider->getLocaleCode($languageId, Context::createDefaultContext());
        $provider->getLocaleCode('another-language-id', Context::createDefaultContext());

        $this->assertSame([$languageId, 'another-language-id'], $repository->getRequestedIds());
    }
}
