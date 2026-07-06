<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Logger;

use Mollie\Shopware\Component\Logger\RecordAnonymizer;
use Mollie\Shopware\Integration\Data\ShopwareTestBehaviour;
use Monolog\Logger;
use Monolog\Processor\WebProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

#[CoversClass(RecordAnonymizer::class)]
final class RecordAnonymizerOrderTest extends TestCase
{
    use ShopwareTestBehaviour;
    use IntegrationTestBehaviour;

    /**
     * The WebProcessor adds extra[ip]/extra[url]. The RecordAnonymizer can only anonymize those
     * values if it runs after the WebProcessor, so it must be the last processor on the mollie logger.
     */
    public function testAnonymizerRunsLast(): void
    {
        $logger = $this->getContainer()->get('monolog.logger.mollie');
        self::assertInstanceOf(Logger::class, $logger);

        $processors = $logger->getProcessors();

        $anonymizerIndex = null;
        $webProcessorIndex = null;
        foreach ($processors as $index => $processor) {
            if ($processor instanceof RecordAnonymizer) {
                $anonymizerIndex = $index;
            }
            if ($processor instanceof WebProcessor) {
                $webProcessorIndex = $index;
            }
        }

        self::assertNotNull($anonymizerIndex, 'RecordAnonymizer is not registered on the mollie logger');
        self::assertNotNull($webProcessorIndex, 'WebProcessor is not registered on the mollie logger');
        self::assertGreaterThan($webProcessorIndex, $anonymizerIndex, 'RecordAnonymizer must run after the WebProcessor');
        self::assertSame(count($processors) - 1, $anonymizerIndex, 'RecordAnonymizer must be the last processor');
    }
}
