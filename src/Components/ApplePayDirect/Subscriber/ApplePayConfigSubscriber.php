<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\ApplePayDirect\Subscriber;

use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class ApplePayConfigSubscriber
 *
 * This subscriber is responsible for setting the default configuration
 * for Apple Pay validation allow list upon plugin installation and update.
 */
class ApplePayConfigSubscriber implements EventSubscriberInterface
{
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var EntityRepository
     */
    private $salesChannelDomainRepository;

    /**
     * ApplePayConfigSubscriber constructor.
     *
     * @param SystemConfigService $systemConfigService
     * @param EntityRepository $salesChannelDomainRepository
     */
    public function __construct(SystemConfigService $systemConfigService, EntityRepository $salesChannelDomainRepository)
    {
        $this->systemConfigService = $systemConfigService;
        $this->salesChannelDomainRepository = $salesChannelDomainRepository;
    }

    /**
     * Registers the events the subscriber listens to.
     */
    public static function getSubscribedEvents()
    {
        return [
            PluginPostInstallEvent::class => 'setDefaultConfig',
            PluginPostUpdateEvent::class => 'setDefaultConfig',
        ];
    }

    /**
     * Sets the default configuration for Apple Pay validation allow list.
     *
     * This method retrieves all sales channel domains and constructs a comma-separated
     * list of their URLs. It then sets this list as the default value for the
     * Apple Pay validation allow list configuration.
     *
     * @return void
     */
    public function setDefaultConfig(): void
    {
        // Check if the Apple Pay validation allow list is already set.
        if ($this->validationAllowListIsEmpty() === false) {
            return; // Ensuring to not overwrite the existing configuration.
        }

        // Create a new context with a system source.
        $context = new Context(new SystemSource());

        // Define criteria to fetch sales channel domains.
        $criteria = new Criteria();
        $criteria->addAssociation('salesChannel');

        // Fetch sales channel domains using the repository.
        $domains = $this->salesChannelDomainRepository->search($criteria, $context);
        $usedDomains = [];

        foreach ($domains as $domain) {
            if (!$domain instanceof SalesChannelDomainEntity) {
                continue;
            }
            $usedDomains[] = $domain->getUrl();
        }

        if (count($usedDomains)) {
            // Convert the array of URLs to a comma-separated string.
            $usedDomainsString = implode(',', $usedDomains);

            // Set the configuration value for the Apple Pay validation allow list.
            $this->systemConfigService->set('MolliePayments.config.ApplePayValidationAllowList', $usedDomainsString);
        }
    }

    /**
     * Checks if the Apple Pay validation allow list is empty.
     *
     * @return bool
     */
    private function validationAllowListIsEmpty(): bool
    {
        $allowList = $this->systemConfigService->get('MolliePayments.config.ApplePayValidationAllowList');
        return empty($allowList);
    }
}
