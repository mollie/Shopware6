<?php


namespace MolliePayments\Tests\Components\Installer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ForwardCompatibility\Result;
use Kiener\MolliePayments\Components\Subscription\Services\Installer\MailTemplateInstaller;
use Kiener\MolliePayments\Repository\MailTemplateType\MailTemplateTypeRepositoryInterface;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class MailTemplateInstallerTest extends TestCase
{
    /**
     * @var MailTemplateInstaller
     */
    protected $mailTemplateInstaller;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var MailTemplateTypeRepositoryInterface
     */
    protected $repoMailTypes;

    /**
     * @var EntityRepository|EntityRepositoryInterface
     */
    protected $repoMailTemplates;

    /**
     * @var EntityRepository|EntityRepositoryInterface
     */
    protected $repoSalesChannels;

    public function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->repoMailTypes = $this->createMock(MailTemplateTypeRepositoryInterface::class);
        $this->repoMailTemplates = $this->createMock(EntityRepository::class);
        $this->repoSalesChannels = $this->createMock(EntityRepository::class);

        $salesChannelSearchResult = $this->createConfiguredMock(EntitySearchResult::class, [
            'first' => $this->createMock(SalesChannelEntity::class),
        ]);

        $this->repoSalesChannels->method('search')->willReturn($salesChannelSearchResult);

        $this->mailTemplateInstaller = new MailTemplateInstaller(
            $this->connection,
            $this->repoMailTypes,
            $this->repoMailTemplates,
            $this->repoSalesChannels,
        );
    }

    /**
     * Tests that nothing new is inserted into the database if we have existing MailType and MailTemplate
     *
     * @return void
     * @throws \Doctrine\DBAL\Exception
     */
    public function testWithExistingData()
    {
        // Setup
        $this->setupMailTypeRepoWithExistingData('foo');
        $this->setupMailTemplateRepoWithExistingData('foo');

        $this->connection->expects($this->never())->method('insert');
        $this->repoMailTypes
            ->expects($this->once())
            ->method('update')
            ->with($this->isType(IsType::TYPE_ARRAY));

        $this->mailTemplateInstaller->install(Context::createDefaultContext());
    }

    # -----------------------------------------------------------------------------------------------------------------

    /**
     * Tests creating MailType when the system default language is not English or German
     *
     * @return void
     * @throws \Doctrine\DBAL\Exception
     */
    public function testCreateMailTypeWhereDefaultLangIsNotEnglishOrGerman()
    {
        $this->setupMailTypeRepoWithoutData();
        $this->setupMailTemplateRepoWithExistingData('foo');

        $enLangId = 'foo';
        $deLangId = 'bar';

        $this->setupConnection($enLangId, $deLangId);

        $this->connection
            ->expects($this->exactly(4))
            ->method('insert')
            ->withConsecutive(
                [$this->equalTo('mail_template_type'), $this->isType(IsType::TYPE_ARRAY)],
                [$this->equalTo('mail_template_type_translation'), $this->containsEqual($enLangId)],
                [$this->equalTo('mail_template_type_translation'), $this->containsEqual($deLangId)],
                [
                    $this->equalTo('mail_template_type_translation'),
                    $this->containsEqual(Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM))
                ],
            );

        $this->repoMailTypes
            ->expects($this->once())
            ->method('update')
            ->with($this->isType(IsType::TYPE_ARRAY));

        $this->mailTemplateInstaller->install(Context::createDefaultContext());
    }

    /**
     * Tests creating MailType when the system default language is English
     *
     * @return void
     * @throws \Doctrine\DBAL\Exception
     */
    public function testCreateMailTypeWhereDefaultLangIsEnglish()
    {
        $this->setupMailTypeRepoWithoutData();
        $this->setupMailTemplateRepoWithExistingData('foo');

        $enLangId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $deLangId = 'bar';

        $this->setupConnection($enLangId, $deLangId);

        $this->connection
            ->expects($this->exactly(3))
            ->method('insert')
            ->withConsecutive(
                [$this->equalTo('mail_template_type'), $this->isType(IsType::TYPE_ARRAY)],
                [$this->equalTo('mail_template_type_translation'), $this->containsEqual($enLangId)],
                [$this->equalTo('mail_template_type_translation'), $this->containsEqual($deLangId)],
            );

        $this->repoMailTypes
            ->expects($this->once())
            ->method('update')
            ->with($this->isType(IsType::TYPE_ARRAY));

        $this->mailTemplateInstaller->install(Context::createDefaultContext());
    }

    /**
     * Tests creating MailType when the system default language is German
     *
     * @return void
     * @throws \Doctrine\DBAL\Exception
     */
    public function testCreateMailTypeWhereDefaultLangIsGerman()
    {
        $this->setupMailTypeRepoWithoutData();
        $this->setupMailTemplateRepoWithExistingData('foo');

        $enLangId = 'foo';
        $deLangId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $this->setupConnection($enLangId, $deLangId);

        $this->connection
            ->expects($this->exactly(3))
            ->method('insert')
            ->withConsecutive(
                [$this->equalTo('mail_template_type'), $this->isType(IsType::TYPE_ARRAY)],
                [$this->equalTo('mail_template_type_translation'), $this->containsEqual($enLangId)],
                [$this->equalTo('mail_template_type_translation'), $this->containsEqual($deLangId)],
            );

        $this->repoMailTypes
            ->expects($this->once())
            ->method('update')
            ->with($this->isType(IsType::TYPE_ARRAY));

        $this->mailTemplateInstaller->install(Context::createDefaultContext());
    }

    # -----------------------------------------------------------------------------------------------------------------

    /**
     * Tests creating MailTemplate when the system default language is not English or German
     *
     * @return void
     * @throws \Doctrine\DBAL\Exception
     */
    public function testCreateMailTemplateWhereDefaultLangIsNotEnglishOrGerman()
    {
        $this->setupMailTypeRepoWithExistingData(Uuid::randomHex()); // Cannot use a dummy value like 'foo'
        $this->setupMailTemplateRepoWithoutData();

        $enLangId = 'foo';
        $deLangId = 'bar';

        $this->setupConnection($enLangId, $deLangId);

        $this->connection
            ->expects($this->exactly(4))
            ->method('insert')
            ->withConsecutive(
                [$this->equalTo('mail_template'), $this->isType(IsType::TYPE_ARRAY)],
                [$this->equalTo('mail_template_translation'), $this->containsEqual($enLangId)],
                [$this->equalTo('mail_template_translation'), $this->containsEqual($deLangId)],
                [
                    $this->equalTo('mail_template_translation'),
                    $this->containsEqual(Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM))
                ],
            );

        $this->repoMailTypes
            ->expects($this->once())
            ->method('update')
            ->with($this->isType(IsType::TYPE_ARRAY));

        $this->mailTemplateInstaller->install(Context::createDefaultContext());
    }

    /**
     * Tests creating MailTemplate when the system default language is English
     *
     * @return void
     * @throws \Doctrine\DBAL\Exception
     */
    public function testCreateMailTemplateWhereDefaultLangIsEnglish()
    {
        $this->setupMailTypeRepoWithExistingData(Uuid::randomHex()); // Cannot use a dummy value like 'foo'
        $this->setupMailTemplateRepoWithoutData();

        $enLangId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $deLangId = 'bar';

        $this->setupConnection($enLangId, $deLangId);

        $this->connection
            ->expects($this->exactly(3))
            ->method('insert')
            ->withConsecutive(
                [$this->equalTo('mail_template'), $this->isType(IsType::TYPE_ARRAY)],
                [$this->equalTo('mail_template_translation'), $this->containsEqual($enLangId)],
                [$this->equalTo('mail_template_translation'), $this->containsEqual($deLangId)],
            );

        $this->repoMailTypes
            ->expects($this->once())
            ->method('update')
            ->with($this->isType(IsType::TYPE_ARRAY));

        $this->mailTemplateInstaller->install(Context::createDefaultContext());
    }

    /**
     * Tests creating MailTemplate when the system default language is German
     *
     * @return void
     * @throws \Doctrine\DBAL\Exception
     */
    public function testCreateMailTemplateWhereDefaultLangIsGerman()
    {
        $this->setupMailTypeRepoWithExistingData(Uuid::randomHex()); // Cannot use a dummy value like 'foo'
        $this->setupMailTemplateRepoWithoutData();

        $enLangId = 'foo';
        $deLangId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $this->setupConnection($enLangId, $deLangId);

        $this->connection
            ->expects($this->exactly(3))
            ->method('insert')
            ->withConsecutive(
                [$this->equalTo('mail_template'), $this->isType(IsType::TYPE_ARRAY)],
                [$this->equalTo('mail_template_translation'), $this->containsEqual($enLangId)],
                [$this->equalTo('mail_template_translation'), $this->containsEqual($deLangId)],
            );

        $this->repoMailTypes
            ->expects($this->once())
            ->method('update')
            ->with($this->isType(IsType::TYPE_ARRAY));

        $this->mailTemplateInstaller->install(Context::createDefaultContext());
    }

    # ----Connection----------------------------------------
    private function setupConnection($enLangId, $deLangId)
    {
        $enResult = $this->createConfiguredMock(Result::class, [
            'fetchColumn' => $enLangId,
            'fetchOne' => $enLangId,
        ]);
        $deResult = $this->createConfiguredMock(Result::class, [
            'fetchColumn' => $deLangId,
            'fetchOne' => $deLangId,
        ]);

        $this->connection
            ->expects($this->atLeast(2))
            ->method('executeQuery')
            ->withConsecutive(
                [$this->isType(IsType::TYPE_STRING), $this->containsEqual('en-GB')],
                [$this->isType(IsType::TYPE_STRING), $this->containsEqual('de-DE')],
            )
            ->willReturnOnConsecutiveCalls($enResult, $deResult);
    }

    # ----MailType----------------------------------------
    private function setupMailTypeRepoWithoutData()
    {
        $result = $this->createConfiguredMock(IdSearchResult::class, [
            'getIds' => [],
        ]);

        $this->repoMailTypes->method('searchIds')->willReturn($result);
        $this->repoMailTypes->expects($this->once())->method('searchIds');
    }

    private function setupMailTypeRepoWithExistingData($id)
    {
        $result = $this->createConfiguredMock(IdSearchResult::class, [
            'getIds' => [$id],
            'firstId' => $id,
        ]);

        $this->repoMailTypes->method('searchIds')->willReturn($result);
        $this->repoMailTypes->expects($this->once())->method('searchIds');
    }

    # ----MailTemplate----------------------------------------
    private function setupMailTemplateRepoWithoutData()
    {
        $result = $this->createConfiguredMock(IdSearchResult::class, [
            'getIds' => [],
        ]);

        $this->repoMailTemplates->method('searchIds')->willReturn($result);
        $this->repoMailTemplates->expects($this->once())->method('searchIds');
    }

    private function setupMailTemplateRepoWithExistingData($id)
    {
        $result = $this->createConfiguredMock(IdSearchResult::class, [
            'getIds' => [$id],
            'firstId' => $id,
        ]);

        $this->repoMailTemplates->method('searchIds')->willReturn($result);
        $this->repoMailTemplates->expects($this->once())->method('searchIds');
    }

}
