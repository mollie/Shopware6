<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\MetaInformation;
use Shopware\Storefront\Page\Page;
use Symfony\Component\HttpFoundation\Request;

final class FakeGenericPageLoader implements GenericPageLoaderInterface
{
    /**
     * Without meta information the page behaves like one Shopware could not resolve a page title for.
     */
    public function __construct(private readonly ?MetaInformation $metaInformation = null)
    {
    }

    public function load(Request $request, SalesChannelContext $context): Page
    {
        $page = new Page();

        if ($this->metaInformation instanceof MetaInformation) {
            $page->setMetaInformation($this->metaInformation);
        }

        return $page;
    }
}
