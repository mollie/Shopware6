<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Logger;

use Mollie\Shopware\Component\Logger\RecordAnonymizer;
use Mollie\Shopware\Integration\Data\ShopwareTestBehaviour;
use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
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
     * The WebProcessor adds extra[ip]/extra[url] and the IntrospectionProcessor adds caller info.
     * The RecordAnonymizer can only anonymize those values if it runs after every processor the
     * plugin registers, so it must run after both of them on the mollie logger.
     *
     * We deliberately do not assert it is the globally last processor: on some Shopware versions
     * (e.g. 6.5) the platform appends an additional processor after the plugin's, which the plugin
     * does not control.
     */
    public function testAnonymizerRunsAfterPluginProcessors(): void
    {
        $logger = $this->getContainer()->get('monolog.logger.mollie');
        self::assertInstanceOf(Logger::class, $logger);

        $processors = $logger->getProcessors();

        $anonymizerIndex = null;
        $webProcessorIndex = null;
        $introspectionProcessorIndex = null;
        foreach ($processors as $index => $processor) {
            if ($processor instanceof RecordAnonymizer) {
                $anonymizerIndex = $index;
            }
            if ($processor instanceof WebProcessor) {
                $webProcessorIndex = $index;
            }
            if ($processor instanceof IntrospectionProcessor) {
                $introspectionProcessorIndex = $index;
            }
        }

        self::assertNotNull($anonymizerIndex, 'RecordAnonymizer is not registered on the mollie logger');
        self::assertNotNull($webProcessorIndex, 'WebProcessor is not registered on the mollie logger');
        self::assertNotNull($introspectionProcessorIndex, 'IntrospectionProcessor is not registered on the mollie logger');
        self::assertGreaterThan($webProcessorIndex, $anonymizerIndex, 'RecordAnonymizer must run after the WebProcessor');
        self::assertGreaterThan($introspectionProcessorIndex, $anonymizerIndex, 'RecordAnonymizer must run after the IntrospectionProcessor');
    }
}
