<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Support\Attachment\Generator;

use Mollie\Shopware\Component\Support\Attachment\Attachment;
use Mollie\Shopware\Component\Support\Attachment\Generator\LogArchiveGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

/**
 * The log archive is what support reads to see what actually happened. It must contain the Mollie
 * logs and nothing else - the shop's other log files can hold unrelated customer data.
 */
#[CoversClass(LogArchiveGenerator::class)]
final class LogArchiveGeneratorTest extends TestCase
{
    private string $logDirectory;

    protected function setUp(): void
    {
        $this->logDirectory = sys_get_temp_dir() . '/mollie-log-archive-test-' . uniqid('', true);
        mkdir($this->logDirectory . '/mollie', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->logDirectory . '/mollie/*') ?: [] as $file) {
            unlink($file);
        }
        foreach (glob($this->logDirectory . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->logDirectory . '/mollie');
        rmdir($this->logDirectory);
    }

    public function testTheMollieLogsAreInTheArchive(): void
    {
        $this->writeLog('mollie_prod.log', 'the mollie log');

        $this->assertSame(['mollie_prod.log'], $this->fileNamesInArchive());
    }

    /**
     * Shopware's own logs can hold data of other plugins and their customers; only the Mollie ones
     * may leave the shop.
     */
    public function testAnotherPluginsLogIsNotInTheArchive(): void
    {
        $this->writeLog('mollie_prod.log', 'the mollie log');
        $this->writeLog('prod.log', 'someone else');

        $this->assertSame(['mollie_prod.log'], $this->fileNamesInArchive());
    }

    /**
     * The per-order logs live in their own subdirectory and have to keep it inside the archive, or
     * they would collide with the main log names.
     */
    public function testThePerOrderLogsKeepTheirSubdirectory(): void
    {
        $this->writeLog('mollie/order-10000.log', 'order log');

        $this->assertSame(['mollie/order-10000.log'], $this->fileNamesInArchive());
    }

    public function testAShopThatNeverLoggedAnythingStillProducesAnArchive(): void
    {
        $attachment = $this->generate();

        $this->assertSame('mollie_logs.zip', $attachment->fileName);
        $this->assertSame([], $this->fileNamesInArchive());
    }

    /**
     * The archive is built inside the log directory and has to be cleaned up, or every support
     * request leaves a copy of all logs behind.
     */
    public function testTheArchiveIsNotLeftBehindInTheLogDirectory(): void
    {
        $this->writeLog('mollie_prod.log', 'the mollie log');

        $this->generate();

        $this->assertFileDoesNotExist($this->logDirectory . '/mollie_logs.zip');
    }

    /**
     * @return list<string>
     */
    private function fileNamesInArchive(): array
    {
        $attachment = $this->generate();

        if ($attachment->content === '') {
            return [];
        }

        $archivePath = $this->logDirectory . '/read-back.zip';
        file_put_contents($archivePath, $attachment->content);

        $archive = new \ZipArchive();
        $archive->open($archivePath);

        $names = [];
        for ($index = 0; $index < $archive->numFiles; ++$index) {
            $names[] = (string) $archive->getNameIndex($index);
        }
        $archive->close();
        unlink($archivePath);

        sort($names);

        return $names;
    }

    private function generate(): Attachment
    {
        return (new LogArchiveGenerator($this->logDirectory, 'mollie_'))->generate(Context::createDefaultContext());
    }

    private function writeLog(string $relativePath, string $content): void
    {
        file_put_contents($this->logDirectory . '/' . $relativePath, $content);
    }
}
