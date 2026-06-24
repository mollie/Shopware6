<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings\Route;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Locale;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['store-api']])]
final class ConfigRoute
{
    /**
     * @param EntityRepository<EntityCollection<LanguageEntity>> $languageRepository
     */
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: MollieGateway::class)]
        private MollieGatewayInterface $mollieGateway,
        #[Autowire(service: 'language.repository')]
        private EntityRepository $languageRepository,
    ) {
    }

    #[Route(path: '/store-api/mollie/config', name: 'store-api.mollie.config', methods: ['GET'])]
    public function getConfig(SalesChannelContext $context): ConfigResponse
    {
        $salesChannelId = $context->getSalesChannelId();

        $apiSettings = $this->settingsService->getApiSettings($salesChannelId);
        $paymentSettings = $this->settingsService->getPaymentSettings($salesChannelId);

        $profileId = $apiSettings->getProfileId();
        if ($profileId === '') {
            $profile = $this->mollieGateway->getCurrentProfile($salesChannelId);
            $profileId = $profile->getId();
        }

        return new ConfigResponse(
            $profileId,
            $apiSettings->isTestMode(),
            $this->resolveLocale($context),
            $paymentSettings->isOneClickPayment()
        );
    }

    private function resolveLocale(SalesChannelContext $context): string
    {
        $customer = $context->getCustomer();
        if ($customer instanceof CustomerEntity) {
            $languageId = $customer->getLanguageId();
        } else {
            $languageId = $context->getSalesChannel()->getLanguageId();
        }

        $criteria = new Criteria([$languageId]);
        $criteria->addAssociation('locale');

        /** @var ?LanguageEntity $language */
        $language = $this->languageRepository->search($criteria, $context->getContext())->first();

        if ($language === null) {
            return Locale::enGB->value;
        }

        try {
            return Locale::fromLanguage($language)->value;
        } catch (\ValueError) {
            return Locale::enGB->value;
        }
    }
}
