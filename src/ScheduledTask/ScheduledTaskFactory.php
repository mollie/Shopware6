<?php

namespace Kiener\MolliePayments\ScheduledTask;

use Kiener\MolliePayments\ScheduledTask\Subscription\RenewalReminder\RenewalReminderTask;
use Kiener\MolliePayments\ScheduledTask\Subscription\RenewalReminder\RenewalReminderTaskDev;
use Kiener\MolliePayments\Service\PluginSettingsServiceInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class ScheduledTaskFactory
{
    /**
     * @var PluginSettingsServiceInterface
     */
    private $pluginSetting;


    /**
     * @param PluginSettingsServiceInterface $pluginSetting
     */
    public function __construct(PluginSettingsServiceInterface $pluginSetting)
    {
        $this->pluginSetting = $pluginSetting;
    }


    /**
     * @return ScheduledTask
     */
    public function createRenewalReminderTask(): ScheduledTask
    {
        if ($this->pluginSetting->getEnvMollieDevMode()) {
            return new RenewalReminderTaskDev();
        }

        return new RenewalReminderTask();
    }
}
