<?php

namespace Kiener\MolliePayments\Components\Subscription\Services\Installer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Repository\MailTemplate\MailTemplateRepositoryInterface;
use Kiener\MolliePayments\Repository\MailTemplateType\MailTemplateTypeRepositoryInterface;
use Kiener\MolliePayments\Repository\SalesChannel\SalesChannelRepositoryInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class MailTemplateInstaller
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var MailTemplateTypeRepositoryInterface
     */
    private $repoMailTypes;

    /**
     * @var MailTemplateRepositoryInterface
     */
    private $repoMailTemplates;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $repoSalesChannels;

    /**
     * @param Connection $connection
     * @param MailTemplateTypeRepositoryInterface $repoMailTypes
     * @param MailTemplateRepositoryInterface $repoMailTemplates
     * @param SalesChannelRepositoryInterface $repoSalesChannels
     */
    public function __construct(Connection $connection, MailTemplateTypeRepositoryInterface $repoMailTypes, MailTemplateRepositoryInterface $repoMailTemplates, SalesChannelRepositoryInterface $repoSalesChannels)
    {
        $this->connection = $connection;
        $this->repoMailTypes = $repoMailTypes;
        $this->repoMailTemplates = $repoMailTemplates;
        $this->repoSalesChannels = $repoSalesChannels;
    }


    /**
     * @throws Exception
     */
    public function install(Context $context): void
    {
        # create a new mail template type
        # if it's not already existing
        $reminderTypeID = $this->getReminderMailTypeID($context);

        if (empty($reminderTypeID)) {
            $reminderTypeID = $this->createMailTemplateType($this->connection);
        }

        # only create a template if the merchant
        # does not already have one
        $existingMailTemplateID = $this->getReminderTemplateID($reminderTypeID, $context);

        if (empty($existingMailTemplateID)) {
            $this->createMailTemplate($this->connection, $reminderTypeID);
        }

        # ----------------------------------------------------------------------------------------------
        # update our sample data for the admin mail preview

        $subscription = new SubscriptionEntity();
        $subscription->setDescription('1x Sample Product (Order #1233, 24.99 EUR)');
        $subscription->setQuantity(1);
        $subscription->setAmount(24.99);
        $subscription->setCurrency('EUR');
        $subscription->setMollieCustomerId('cst_123456');
        $subscription->setMollieId('sub_123456');
        $subscription->setNextPaymentAt(new \DateTime());

        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $this->repoSalesChannels->search(new Criteria(), $context)->first();
        $salesChannel->setName('Demo Shop');

        $customer = new CustomerEntity();
        $customer->setFirstName('John');
        $customer->setLastName('Doe');


        $this->repoMailTypes->update(
            [
                [
                    'id' => $reminderTypeID,
                    'templateData' => [
                        'customer' => $customer,
                        'subscription' => $subscription,
                        'salesChannel' => $salesChannel,
                    ]
                ]
            ],
            $context
        );
    }


    /**
     * @param Context $context
     * @return string
     */
    private function getReminderMailTypeID(Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', 'mollie_subscriptions_renewal_reminder'));

        $result = $this->repoMailTypes->searchIds($criteria, $context);

        if (count($result->getIds()) <= 0) {
            return '';
        }

        return (string)$result->firstId();
    }

    /**
     * @param string $typeID
     * @param Context $context
     * @return string
     */
    private function getReminderTemplateID(string $typeID, Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mailTemplateTypeId', $typeID));

        $result = $this->repoMailTemplates->searchIds($criteria, $context);

        if (count($result->getIds()) <= 0) {
            return '';
        }

        return (string)$result->firstId();
    }

    /**
     * @param Connection $connection
     * @throws Exception
     * @return string
     */
    private function createMailTemplateType(Connection $connection): string
    {
        $technicalName = 'mollie_subscriptions_renewal_reminder';

        $mailTemplateTypeId = Uuid::randomHex();

        $englishName = 'Subscription Renewal Reminder (Mollie)';
        $germanName = 'Erinnerung der Abonnementverlängerung (Mollie)';

        $enLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deLangId = $this->getLanguageIdByLocale($connection, 'de-DE');

        # -----------------------------------------------------------------------------------------------------

        $connection->insert('mail_template_type', [
            'id' => Uuid::fromHexToBytes($mailTemplateTypeId),
            'technical_name' => $technicalName,
            'available_entities' => json_encode([
                'customer' => 'customer',
                'subscription' => 'mollie_subscription',
                'salesChannel' => 'sales_channel'
            ]),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        # -----------------------------------------------------------------------------------------------------

        // If we have an english language ID then insert the english translation
        if (!empty($enLangId)) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'language_id' => $enLangId,
                'name' => $englishName,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        // If we have a german language ID then insert the german translation
        if (!empty($deLangId)) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'language_id' => $deLangId,
                'name' => $germanName,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        // If the system default language is not english OR german, insert the english translation for it
        if (!in_array(Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), [$enLangId, $deLangId])) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'name' => $englishName,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        return $mailTemplateTypeId;
    }

    /**
     * @param Connection $connection
     * @param string $mailTemplateTypeId
     * @throws Exception
     * @return void
     */
    private function createMailTemplate(Connection $connection, string $mailTemplateTypeId): void
    {
        $mailTemplateId = Uuid::randomHex();

        $sender = '{{ salesChannel.name }}';

        $subjectEN = 'Your subscription from {{ salesChannel.name }} will be renewed soon';
        $descriptionEN = 'Subscription Renewal Reminder Mail';
        $contentHtmlEN = file_get_contents(__DIR__ . '/Mails/RenewalReminder/en.html');
        $contentPlainEN = file_get_contents(__DIR__ . '/Mails/RenewalReminder/en.txt');

        $subjectDE = 'Ihr Abonnement von {{ salesChannel.name }} wird in Kürze verlängert';
        $descriptionDE = 'Erinnerungsmail zur Verlängerung des Abonnements';
        $contentHtmlDE = file_get_contents(__DIR__ . '/Mails/RenewalReminder/de.html');
        $contentPlainDE = file_get_contents(__DIR__ . '/Mails/RenewalReminder/de.txt');

        # -----------------------------------------------------------------------------------------------------

        $enLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deLangId = $this->getLanguageIdByLocale($connection, 'de-DE');

        # -----------------------------------------------------------------------------------------------------

        $connection->insert('mail_template', [
            'id' => Uuid::fromHexToBytes($mailTemplateId),
            'mail_template_type_id' => Uuid::fromHexToBytes($mailTemplateTypeId),
            'system_default' => 0,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        # -----------------------------------------------------------------------------------------------------

        // If we have an english language ID then insert the english translation
        if (!empty($enLangId)) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => Uuid::fromHexToBytes($mailTemplateId),
                'language_id' => $enLangId,
                'sender_name' => $sender,
                'subject' => $subjectEN,
                'description' => $descriptionEN,
                'content_html' => $contentHtmlEN,
                'content_plain' => $contentPlainEN,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        # -----------------------------------------------------------------------------------------------------

        // If we have a german language ID then insert the german translation
        if (!empty($deLangId)) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => Uuid::fromHexToBytes($mailTemplateId),
                'language_id' => $deLangId,
                'sender_name' => $sender,
                'subject' => $subjectDE,
                'description' => $descriptionDE,
                'content_html' => $contentHtmlDE,
                'content_plain' => $contentPlainDE,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        # -----------------------------------------------------------------------------------------------------

        // If the system default language is not english OR german, insert the english translation for it
        if (!in_array(Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), [$enLangId, $deLangId])) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => Uuid::fromHexToBytes($mailTemplateId),
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'sender_name' => $sender,
                'subject' => $subjectEN,
                'description' => $descriptionEN,
                'content_html' => $contentHtmlEN,
                'content_plain' => $contentPlainEN,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }

    /**
     * @param Connection $connection
     * @param string $locale
     * @throws Exception
     * @return null|string
     */
    private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
    {
        $sql = "
                SELECT `language`.`id`
                FROM `language`
                INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
                WHERE `locale`.`code` = :code
                ";

        $languageId = $connection->executeQuery($sql, ['code' => $locale])->fetchOne();

        if (!$languageId) {
            return null;
        }

        return (string)$languageId;
    }
}
