<?php declare(strict_types=1);
namespace Kiener\MolliePayments\Service\Subscription;

use Kiener\MolliePayments\Service\ConfigService;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

class EmailService
{
    const LIVE_MODE = 'live';
    const TEST_MODE = 'test';

    /**
     * @var Mailer
     */
    private $mailer;

    /**
     * @var EntityRepositoryInterface
     */
    private $mailTemplateRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * @var EntityRepositoryInterface
     */
    private $customer;

    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var EntityRepositoryInterface
     */
    private $product;

    /**
     * @var LoggerService
     */
    private $logger;

    /**
     * @param Mailer $mailer
     * @param EntityRepositoryInterface $salesChannelRepository
     * @param EntityRepositoryInterface $customer
     * @param SettingsService $settingsService
     * @param EntityRepositoryInterface $mailTemplateRepository
     * @param ConfigService $configService
     * @param EntityRepositoryInterface $product
     * @param LoggerService $logger
     */
    public function __construct(
        Mailer $mailer,
        EntityRepositoryInterface $salesChannelRepository,
        EntityRepositoryInterface $customer,
        SettingsService           $settingsService,
        EntityRepositoryInterface $mailTemplateRepository,
        ConfigService             $configService,
        EntityRepositoryInterface $product,
        LoggerService             $logger
    )
    {
        $this->mailer = $mailer;
        $this->mailTemplateRepository = $mailTemplateRepository;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->configService = $configService;
        $this->customer = $customer;
        $this->settingsService = $settingsService;
        $this->product = $product;
        $this->logger = $logger;
    }

    /**
     * @param $subscription
     * @return bool
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     */
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
            '%salutation%' => $customer->getSalutation()->getDisplayName(),
            '%customer_name%' => $customer->getFirstName() . ' ' . $customer->getLastName(),
            '%subscriptions_productName%' => $this->getProductName($subscription->getProductId()),
            '%subscriptions_nextPaymentDate%' => $subscription->get('nextPaymentDate')->format('d/m/Y'),
            '%subscriptions_amount%' => $subscription->getAmount()
        ];

        $data->set('salesChannelId', $subscription->getSalesChannelId());
        $data->set('templateId', $mailTemplate->getId());

        $senderEmail = $this->getSender($data, $data['salesChannelId']);

        try {
            $mail = $this->create(
                $data['subject'],
                [$senderEmail => $data['senderName']],
                $data['recipients'],
                $this->buildContents($data, $templateData),
                [],
                $data,
                null
            );

            $this->mailer->send($mail, null);

            return true;
        } catch (\Exception $e) {
            $this->logger->addEntry(
                "Could not send mail:\n"
                . $e->getMessage() . "\n"
                . 'Error Code:' . $e->getCode() . "\n"
                . "Template data: \n"
                . json_encode($data->all()) . "\n"
            );
        }

        return false;
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

        $criteria = new Criteria();
        $criteria->addAssociation('media.media');
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('mailTemplateTypeId', $templateTypeId));

        return $this->mailTemplateRepository->search($criteria, Context::createDefaultContext())->first();
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

    /**
     * @param string $subject
     * @param array $sender
     * @param array $recipients
     * @param array $contents
     * @param array $attachments
     * @param array $additionalData
     * @param array|null $binAttachments
     * @return Email
     */
    public function create(
        string $subject,
        array $sender,
        array $recipients,
        array $contents,
        array $attachments,
        array $additionalData,
        ?array $binAttachments = null
    ): Email {

        $mail = (new Email())
            ->subject($subject)
            ->from(...$this->formatMailAddresses($sender))
            ->to(...$this->formatMailAddresses($recipients));

        foreach ($contents as $contentType => $data) {
            if ($contentType === 'text/html') {
                $mail->html($data);
            } else {
                $mail->text($data);
            }
        }

        if (isset($binAttachments)) {
            foreach ($binAttachments as $binAttachment) {
                $mail->embed(
                    $binAttachment['content'],
                    $binAttachment['fileName'],
                    $binAttachment['mimeType']
                );
            }
        }

        foreach ($additionalData as $key => $value) {
            switch ($key) {
                case 'recipientsCc':
                    $mail->addCc(...$this->formatMailAddresses([$value => $value]));

                    break;
                case 'recipientsBcc':
                    $mail->addBcc(...$this->formatMailAddresses([$value => $value]));

                    break;
                case 'replyTo':
                    $mail->addReplyTo(...$this->formatMailAddresses([$value => $value]));

                    break;
                case 'returnPath':
                    $mail->returnPath(...$this->formatMailAddresses([$value => $value]));
            }
        }

        return $mail;
    }

    /**
     * Attaches header and footer to given email bodies
     *
     * @param array $data e.g. ['contentHtml' => 'foobar', 'contentPlain' => '<h1>foobar</h1>']
     *
     * @return array e.g. ['text/plain' => '{{foobar}}', 'text/html' => '<h1>{{foobar}}</h1>']
     *
     * @internal
     */
    private function buildContents(array $templateData, array $data): array
    {
        $contentPlain = $this->strReplaceAssoc($templateData, $data['contentPlain']);
        $contentHtml = $this->strReplaceAssoc($templateData, $data['contentHtml']);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $data['salesChannelId']));

        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $this->salesChannelRepository->search($criteria, Context::createDefaultContext())->first();
        if ($salesChannel && $mailHeaderFooter = $salesChannel->getMailHeaderFooter()) {
            $headerPlain = $mailHeaderFooter->getTranslation('headerPlain') ?? '';
            $footerPlain = $mailHeaderFooter->getTranslation('footerPlain') ?? '';
            $headerHtml = $mailHeaderFooter->getTranslation('headerHtml') ?? '';
            $footerHtml = $mailHeaderFooter->getTranslation('footerHtml') ?? '';

            return [
                'text/plain' => sprintf('%s%s%s', $headerPlain, $contentPlain, $footerPlain),
                'text/html' => sprintf('%s%s%s', $headerHtml, $contentHtml, $footerHtml),
            ];
        }

        return [
            'text/html' => $contentHtml,
            'text/plain' => $contentPlain,
        ];
    }

    /**
     * @param array $replace
     * @param $subject
     * @return mixed
     */
    function strReplaceAssoc(array $replace, $subject) {
        return str_replace(array_keys($replace), array_values($replace), $subject);
    }

    /**
     * @param array $data
     * @param string|null $salesChannelId
     * @return string|null
     */
    private function getSender(array $data, ?string $salesChannelId): ?string
    {
        $senderEmail = $data['senderEmail'] ?? null;

        if ($senderEmail === null || trim($senderEmail) === '') {
            $senderEmail = $this->configService->get('core.basicInformation.email', $salesChannelId);
        }

        if ($senderEmail === null || trim($senderEmail) === '') {
            $senderEmail = $this->configService->get('core.mailerSettings.senderAddress', $salesChannelId);
        }

        if ($senderEmail === null || trim($senderEmail) === '') {
            $this->logger->error('senderMail not configured for salesChannel: '
                . $salesChannelId
                . '. Please check system_config \'core.basicInformation.email\'');

            return null;
        }

        return $senderEmail;
    }

    /**
     * @param array $addresses
     * @return array
     */
    private function formatMailAddresses(array $addresses): array
    {
        $formattedAddresses = [];
        foreach ($addresses as $mail => $name) {
            $formattedAddresses[] = $name . ' <' . $mail . '>';
        }

        return $formattedAddresses;
    }
}
