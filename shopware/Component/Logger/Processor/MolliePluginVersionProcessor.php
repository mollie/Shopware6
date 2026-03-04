<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Logger\Processor;

use Kiener\MolliePayments\MolliePayments;
use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

#[AsMonologProcessor('mollie')]
class MolliePluginVersionProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        if ($record->channel === 'mollie') {
            $record->extra['pluginVersion'] = MolliePayments::PLUGIN_VERSION;
        }

        return $record;
    }
}
