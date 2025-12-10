<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Logger\Processor;

use Kiener\MolliePayments\MolliePayments;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class MolliePluginVersionProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $record->extra['pluginVersion'] = MolliePayments::PLUGIN_VERSION;

        return $record;
    }
}