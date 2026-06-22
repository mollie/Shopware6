<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ApplePayDirect;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Service\ApplePayDomainVerificationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApplePayDomainVerificationService::class)]
class ApplePayDomainVerificationServiceTest extends TestCase
{
    /**
     * This test verifies that our download URL of the official Mollie domain verification file
     * is not accidentally changed without recognizing it.
     * This is 1 global file for all merchants.
     */
    public function testDownloadURL(): void
    {
        self::assertEquals(
            'https://www.mollie.com/.well-known/apple-developer-merchantid-domain-association',
            ApplePayDomainVerificationService::URL_FILE
        );
    }

    /**
     * This test verifies the local path of the downloaded domain verification file.
     * This must not be changed and always has to be in the .well-known folder of the public DocRoot.
     */
    public function testLocalFile(): void
    {
        self::assertEquals(
            '/.well-known/apple-developer-merchantid-domain-association',
            ApplePayDomainVerificationService::LOCAL_FILE
        );
    }

    /**
     * A successful download stores the response body at the local path and returns true.
     */
    public function testDownloadStoresFileAndReturnsTrue(): void
    {
        $filesystem = $this->createFilesystem();
        $service = new ApplePayDomainVerificationService(
            $filesystem,
            $this->createClient(new MockHandler([new Response(200, [], 'verification-content')]))
        );

        $result = $service->downloadDomainAssociationFile();

        self::assertTrue($result);
        self::assertTrue($filesystem->has(ApplePayDomainVerificationService::LOCAL_FILE));
        self::assertSame('verification-content', $filesystem->read(ApplePayDomainVerificationService::LOCAL_FILE));
    }

    /**
     * An already existing local file is overwritten with the freshly downloaded content.
     */
    public function testDownloadReplacesExistingFile(): void
    {
        $filesystem = $this->createFilesystem();
        $filesystem->write(ApplePayDomainVerificationService::LOCAL_FILE, 'old-content');

        $service = new ApplePayDomainVerificationService(
            $filesystem,
            $this->createClient(new MockHandler([new Response(200, [], 'new-content')]))
        );

        $result = $service->downloadDomainAssociationFile();

        self::assertTrue($result);
        self::assertSame('new-content', $filesystem->read(ApplePayDomainVerificationService::LOCAL_FILE));
    }

    /**
     * A non successful HTTP status must not write anything and has to return false.
     */
    public function testDownloadReturnsFalseAndWritesNothingOnErrorStatus(): void
    {
        $filesystem = $this->createFilesystem();
        $service = new ApplePayDomainVerificationService(
            $filesystem,
            $this->createClient(new MockHandler([new Response(404, [], 'not found')]))
        );

        $result = $service->downloadDomainAssociationFile();

        self::assertFalse($result);
        self::assertFalse($filesystem->has(ApplePayDomainVerificationService::LOCAL_FILE));
    }

    private function createClient(MockHandler $mockHandler): Client
    {
        $handlerStack = HandlerStack::create($mockHandler);

        return new Client(['handler' => $handlerStack]);
    }

    private function createFilesystem(): Filesystem
    {
        $adapter = new InMemoryFilesystemAdapter();

        return new Filesystem($adapter);
    }
}
