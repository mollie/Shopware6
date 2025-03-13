<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Service;

use Kiener\MolliePayments\Service\DomainExtractor;
use PHPUnit\Framework\TestCase;

class DomainExtractorTest extends TestCase
{
    /**
     * This test verifies that we can correctly
     * extract the clean domain from a HTTP url
     */
    public function testHttp()
    {
        $extractor = new DomainExtractor();

        $domain = $extractor->getCleanDomain('http://www.mollie.com');

        $this->assertEquals('www.mollie.com', $domain);
    }

    /**
     * This test verifies that we can correctly
     * extract the clean domain from a HTTPS url
     */
    public function testHttps()
    {
        $extractor = new DomainExtractor();

        $domain = $extractor->getCleanDomain('https://www.mollie.com');

        $this->assertEquals('www.mollie.com', $domain);
    }

    /**
     * This test verifies that we get the domain if we do not
     * provide any http protocol
     */
    public function testCleanDomain()
    {
        $extractor = new DomainExtractor();

        $domain = $extractor->getCleanDomain('www.mollie.com');

        $this->assertEquals('www.mollie.com', $domain);
    }

    /**
     * This test verifies that we get the root domain
     * without any slugs in the url
     */
    public function testWithSlug()
    {
        $extractor = new DomainExtractor();

        $domain = $extractor->getCleanDomain('www.mollie.com/de');

        $this->assertEquals('www.mollie.com', $domain);
    }

    /**
     * This test verifies that we get the correct domain if
     * no http scheme exists and also if we do have a slug url.
     * We must not get the slug
     */
    public function testCleanDomainWithSlug()
    {
        $extractor = new DomainExtractor();

        $domain = $extractor->getCleanDomain('https://www.mollie.com/de');

        $this->assertEquals('www.mollie.com', $domain);
    }
}
