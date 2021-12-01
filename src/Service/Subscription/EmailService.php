<?php declare(strict_types=1);
namespace Kiener\MolliePayments\Service\Subscription;

use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\Subscription\SalesChannelService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Kiener\MolliePayments\Service\ConfigService;
use Kiener\MolliePayments\Service\LoggerService;

class EmailService
{
    const LIVE_MODE = 'live';
    const TEST_MODE = 'test';

    /**
     * @var AbstractMailService
     */
    private AbstractMailService $mailService;

    /**
     * @var EntityRepositoryInterface
     */
    private EntityRepositoryInterface $mailTemplateRepository;

    /**
     * @var SalesChannelService
     */
    private SalesChannelService $salesChannelService;

    /**
     * @var ConfigService
     */
    private ConfigService $configService;

    /**
     * @var EntityRepositoryInterface
     */
    private EntityRepositoryInterface $customer;

    /**
     * @var SettingsService
     */
    private SettingsService $settingsService;

    /**
     * @var EntityRepositoryInterface
     */
    private EntityRepositoryInterface $product;

    /**
     * @var LoggerService
     */
    private LoggerService $logger;

    /**
     * @param AbstractMailService $mailService
     * @param SalesChannelService $salesChannelService
     * @param EntityRepositoryInterface $customer
     * @param SettingsService $settingsService
     * @param EntityRepositoryInterface $mailTemplateRepository
     * @param ConfigService $configService
     * @param EntityRepositoryInterface $product
     * @param LoggerService $logger
     */
    public function __construct(
        AbstractMailService       $mailService,
        SalesChannelService       $salesChannelService,
        EntityRepositoryInterface $customer,
        SettingsService           $settingsService,
        EntityRepositoryInterface $mailTemplateRepository,
        ConfigService             $configService,
        EntityRepositoryInterface $product,
        LoggerService             $logger
    )
    {
        $this->mailService = $mailService;
        $this->mailTemplateRepository = $mailTemplateRepository;
        $this->salesChannelService = $salesChannelService;
        $this->configService = $configService;
        $this->customer = $customer;
        $this->settingsService = $settingsService;
        $this->product = $product;
        $this->logger = $logger;
    }


    public function sendMail($subscription): bool
    {
        $customer = $this->getCustomer($subscription);
        $mailTemplate = $this->getMailTemplate();

        if (is_null($mailTemplate)) {
            return false;
        }

        $data = new DataBag();
        $data->set('recipients', $customer->getEmail());
        $data->set('senderName', $mailTemplate->getTranslation('senderName'));

        $data->set('customFields', $mailTemplate->getCustomFields());
        $data->set('contentHtml', $mailTemplate->getTranslation('contentHtml'));
        $data->set('contentPlain', $mailTemplate->getTranslation('contentPlain'));
        $data->set('subject', $mailTemplate->getTranslation('subject'));
        $data->set('mediaIds', []);

        $templateData = [
            'subscription' => [
                'customer' => [
                    'salutation' => $customer->getSalutation()->getDisplayName(),
                    'name' => $customer->getFirstName() . ' ' . $customer->getLastName()
                ],
                'productName' => $this->getProductName($subscription->getProductId()),
                'nextPaymentDate' => $subscription->get('nextPaymentDate')->format('d/m/Y'),
                'amount' => $subscription->getAmount()
            ]
        ];

        if (!isset($salesChannelContext)) {
            $salesChannelContext = $this->salesChannelService->createSalesChannelContext();
        }
        $data->set('salesChannelId', $salesChannelContext->getSalesChannel()->getId());

        $data->set('templateId', $mailTemplate->getId());

        try {
            $result = $this->mailService->send($data->all(), $salesChannelContext->getContext(), $templateData);
        } catch (\Exception $e) {
            $this->logger->addEntry(
                "Could not send mail:\n"
                . $e->getMessage() . "\n"
                . 'Error Code:' . $e->getCode() . "\n"
                . "Template data: \n"
                . json_encode($data->all()) . "\n"
            );
        }
        return (bool)$result;
    }

    /**
     * @return MailTemplateEntity|null
     * @throws InconsistentCriteriaIdsException
     */
    private function getMailTemplate(): ?MailTemplateEntity
    {
        $templateTypeId = $this->configService->get(ConfigService::EMAIL_TEMPLATE);
        if (is_null($templateTypeId)) {
            return null;
        }

        if (!isset($context)) {
            $salesChannelContext = $this->salesChannelService->createSalesChannelContext();
            $context = $salesChannelContext->getContext();
        }

        $criteria = new Criteria();
        $criteria->addAssociation('media.media');
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('mailTemplateTypeId', $templateTypeId));

        return $this->mailTemplateRepository->search($criteria, $context)->first();
    }

    /**
     * @param $subscription
     * @return CustomerEntity|null
     */
    private function getCustomer($subscription): ?CustomerEntity
    {
        $criteria = new Criteria();

        $settings = $this->settingsService->getSettings($subscription->salesChannelId);
        $mode = $settings->isTestMode() ? self::TEST_MODE : self::LIVE_MODE;
        $field = 'customFields.mollie_payments.customer_ids' . '.' . $settings->getProfileId() . '.' . $mode;
        $criteria->addFilter(new EqualsAnyFilter($field, [$subscription->mollieCustomerId]));

        return $this->customer->search($criteria, Context::createDefaultContext())->first();
    }

    /**
     * @param $id
     * @return string|null
     */
    private function getProductName($id): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $id));
        $product = $this->product->search($criteria, Context::createDefaultContext())->first();
        return $product->getName();
    }
}
