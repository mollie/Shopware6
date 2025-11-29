<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Components\Installer;

use Doctrine\DBAL\Cache\ArrayResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Kiener\MolliePayments\Components\Subscription\Services\Installer\MailTemplateInstaller;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
     * @var MailTemplateRepositoryInterface
     */
    protected $repoMailTemplates;

    /**
     * @var EntityRepository|EntityRepositoryInterface
     */
    protected $repoSalesChannels;

    public function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);

        $this->repoMailTypes = $this->createMock(EntityRepository::class);
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
     * @throws \Doctrine\DBAL\Exception
     *
     * @return void
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
            ->with($this->isType(IsType::TYPE_ARRAY))
        ;

        $this->mailTemplateInstaller->install(Context::createDefaultContext());
    }

    // -----------------------------------------------------------------------------------------------------------------

    /**
     * Tests creating MailType when the system default language is not English or German
     *
     * @throws \Doctrine\DBAL\Exception
     *
     * @return void
     */
    public function testCreateMailTypeWhereDefaultLangIsNotEnglishOrGerman()
    {
        $this->setupMailTypeRepoWithoutData();
        $this->setupMailTemplateRepoWithExistingData('foo');

        $enLangId = 'foo';
        $deLangId = 'bar';
        $defaultLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $this->setupConnection($enLangId, $deLangId);
        $matcher = $this->exactly(4);
        $this->connection
            ->expects($matcher)
            ->method('insert')
            ->willReturnCallback(function (string $tableName,array $data) use ($matcher,$enLangId,$deLangId,$defaultLanguageId) {
                if ($matcher->numberOfInvocations() === 1) {
                    $this->assertEquals('mail_template_type',$tableName);
                    $this->assertIsArray($data);

                    return 1;
                }
                $this->assertEquals('mail_template_type_translation',$tableName);
                if ($matcher->numberOfInvocations() === 2) {
                    $this->assertContainsEquals($enLangId,$data);

                    return 1;
                }
                if ($matcher->numberOfInvocations() === 3) {
                    $this->assertContainsEquals($deLangId,$data);

                    return 1;
                }
                if ($matcher->numberOfInvocations() === 4) {
                    $this->assertContainsEquals($defaultLanguageId,$data);

                    return 1;
                }
            })
        ;

        $this->repoMailTypes
            ->expects($this->once())
            ->method('update')
            ->with($this->isType(IsType::TYPE_ARRAY))
        ;

        $this->mailTemplateInstaller->install(Context::createDefaultContext());
    }

    /**
     * Tests creating MailType when the system default language is English
     *
     * @throws \Doctrine\DBAL\Exception
     *
     * @return void
     */
    public function testCreateMailTypeWhereDefaultLangIsEnglish()
    {
        $this->setupMailTypeRepoWithoutData();
        $this->setupMailTemplateRepoWithExistingData('foo');

        $enLangId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $deLangId = 'bar';

        $this->setupConnection($enLangId, $deLangId);
        $matcher = $this->exactly(3);

        $this->connection
            ->expects($matcher)
            ->method('insert')
            ->willReturnCallback(function (string $tableName,array $data) use ($matcher,$enLangId,$deLangId) {
                if ($matcher->numberOfInvocations() === 1) {
                    $this->assertEquals('mail_template_type',$tableName);
                    $this->assertIsArray($data);

                    return 1;
                }
                $this->assertEquals('mail_template_type_translation',$tableName);
                if ($matcher->numberOfInvocations() === 2) {
                    $this->assertContainsEquals($enLangId,$data);

                    return 1;
                }
                if ($matcher->numberOfInvocations() === 3) {
                    $this->assertContainsEquals($deLangId,$data);

                    return 1;
                }
            })
        ;

        $this->repoMailTypes
            ->expects($this->once())
            ->method('update')
            ->with($this->isType(IsType::TYPE_ARRAY))
        ;

        $this->mailTemplateInstaller->install(Context::createDefaultContext());
    }

    /**
     * Tests creating MailType when the system default language is German
     *
     * @throws \Doctrine\DBAL\Exception
     *
     * @return void
     */
    public function testCreateMailTypeWhereDefaultLangIsGerman()
    {
        $this->setupMailTypeRepoWithoutData();
        $this->setupMailTemplateRepoWithExistingData('foo');

        $enLangId = 'foo';
        $deLangId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $this->setupConnection($enLangId, $deLangId);
        $matcher = $this->exactly(3);

        $this->connection
            ->expects($matcher)
            ->method('insert')
            ->willReturnCallback(function (string $tableName,array $data) use ($matcher,$enLangId,$deLangId) {
                if ($matcher->numberOfInvocations() === 1) {
                    $this->assertSame('mail_template_type',$tableName);

                    return 1;
                }
                $this->assertSame('mail_template_type_translation',$tableName);
                if ($matcher->numberOfInvocations() === 2) {
                    $this->assertContainsEquals($enLangId,$data);

                    return 1;
                }
                if ($matcher->numberOfInvocations() === 3) {
                    $this->assertContainsEquals($deLangId,$data);

                    return 1;
                }
            })
        ;

        $this->repoMailTypes
            ->expects($this->once())
            ->method('update')
            ->with($this->isType(IsType::TYPE_ARRAY))
        ;

        $this->mailTemplateInstaller->install(Context::createDefaultContext());
    }

    // -----------------------------------------------------------------------------------------------------------------

    /**
     * Tests creating MailTemplate when the system default language is not English or German
     *
     * @throws \Doctrine\DBAL\Exception
     *
     * @return void
     */
    public function testCreateMailTemplateWhereDefaultLangIsNotEnglishOrGerman()
    {
        $this->setupMailTypeRepoWithExistingData(Uuid::randomHex()); // Cannot use a dummy value like 'foo'
        $this->setupMailTemplateRepoWithoutData();

        $enLangId = 'foo';
        $deLangId = 'bar';
        $defaultLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $this->setupConnection($enLangId, $deLangId);
        $matcher = $this->exactly(4);
        $this->connection
            ->expects($matcher)
            ->method('insert')
            ->willReturnCallback(function (string $tableName,array $data) use ($matcher,$enLangId,$deLangId,$defaultLanguageId) {
                if ($matcher->numberOfInvocations() === 1) {
                    $this->assertSame('mail_template',$tableName);
                    $this->assertIsArray($data);

                    return 1;
                }

                $this->assertSame('mail_template_translation',$tableName);
                if ($matcher->numberOfInvocations() === 2) {
                    $this->assertContainsEquals($enLangId,$data);

                    return 1;
                }
                if ($matcher->numberOfInvocations() === 3) {
                    $this->assertContainsEquals($deLangId,$data);

                    return 1;
                }
                if ($matcher->numberOfInvocations() === 4) {
                    $this->assertContainsEquals($defaultLanguageId,$data);

                    return 1;
                }
            })
        ;

        $this->repoMailTypes
            ->expects($this->once())
            ->method('update')
            ->with($this->isType(IsType::TYPE_ARRAY))
        ;

        $this->mailTemplateInstaller->install(Context::createDefaultContext());
    }

    /**
     * Tests creating MailTemplate when the system default language is English
     *
     * @throws \Doctrine\DBAL\Exception
     *
     * @return void
     */
    public function testCreateMailTemplateWhereDefaultLangIsEnglish()
    {
        $this->setupMailTypeRepoWithExistingData(Uuid::randomHex()); // Cannot use a dummy value like 'foo'
        $this->setupMailTemplateRepoWithoutData();

        $enLangId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $deLangId = 'bar';

        $this->setupConnection($enLangId, $deLangId);
        $matcher = $this->exactly(3);
        $this->connection
            ->expects($matcher)
            ->method('insert')
            ->willReturnCallback(function (string $tableName,array $data) use ($matcher,$enLangId,$deLangId) {
                if ($matcher->numberOfInvocations() === 1) {
                    $this->assertSame('mail_template',$tableName);
                    $this->assertIsArray($data);

                    return 1;
                }
                $this->assertSame('mail_template_translation',$tableName);
                if ($matcher->numberOfInvocations() === 2) {
                    $this->assertContainsEquals($enLangId,$data);

                    return 1;
                }
                if ($matcher->numberOfInvocations() === 3) {
                    $this->assertContainsEquals($deLangId,$data);

                    return 1;
                }
            })
        ;

        $this->repoMailTypes
            ->expects($this->once())
            ->method('update')
            ->with($this->isType(IsType::TYPE_ARRAY))
        ;

        $this->mailTemplateInstaller->install(Context::createDefaultContext());
    }

    /**
     * Tests creating MailTemplate when the system default language is German
     *
     * @throws \Doctrine\DBAL\Exception
     *
     * @return void
     */
    public function testCreateMailTemplateWhereDefaultLangIsGerman()
    {
        $this->setupMailTypeRepoWithExistingData(Uuid::randomHex()); // Cannot use a dummy value like 'foo'
        $this->setupMailTemplateRepoWithoutData();

        $enLangId = 'foo';
        $deLangId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $this->setupConnection($enLangId, $deLangId);
        $matcher = $this->exactly(3);
        $this->connection
            ->expects($matcher)
            ->method('insert')
            ->willReturnCallback(function (string $tableName,array $data) use ($matcher,$enLangId,$deLangId) {
                if ($matcher->numberOfInvocations() === 1) {
                    $this->assertSame($tableName,'mail_template');
                    $this->assertIsArray($data);

                    return 1;
                }
                $this->assertEquals('mail_template_translation',$tableName);
                if ($matcher->numberOfInvocations() === 2) {
                    $this->assertContainsEquals($enLangId,$data);

                    return 1;
                }
                if ($matcher->numberOfInvocations() === 3) {
                    $this->assertContains($deLangId,$data);

                    return 1;
                }
            })
        ;

        $this->repoMailTypes
            ->expects($this->once())
            ->method('update')
            ->with($this->isType(IsType::TYPE_ARRAY))
        ;

        $this->mailTemplateInstaller->install(Context::createDefaultContext());
    }

    // ----Connection----------------------------------------
    private function setupConnection($enLangId, $deLangId)
    {
        $enResult = new Result(new ArrayResult([],[[$enLangId]]),$this->createMock(Connection::class));
        $deResult = new Result(new ArrayResult([],[[$deLangId]]),$this->createMock(Connection::class));

        $matcher = $this->atLeast(2);
        $this->connection
            ->expects($matcher)
            ->method('executeQuery')
            ->willReturnCallback(function (string $sql,array $parameters) use ($enResult, $deResult) {
                $code = $parameters['code'];

                if ($code === 'en-GB') {
                    return $enResult;
                }
                if ($code === 'de-DE') {
                    return $deResult;
                }
            })
        ;
    }

    // ----MailType----------------------------------------
    private function setupMailTypeRepoWithoutData()
    {
        $result = new IdSearchResult(0,[],new Criteria(),Context::createDefaultContext());

        $this->repoMailTypes->method('searchIds')->willReturn($result);
        $this->repoMailTypes->expects($this->once())->method('searchIds');
    }

    private function setupMailTypeRepoWithExistingData($id)
    {
        $data = [
            'primaryKey' => $id,
            'data' => [
                'id' => $id,
            ]
        ];
        $result = new IdSearchResult(1,[$data],new Criteria(),Context::createDefaultContext());

        $this->repoMailTypes->method('searchIds')->willReturn($result);
        $this->repoMailTypes->expects($this->once())->method('searchIds');
    }

    // ----MailTemplate----------------------------------------
    private function setupMailTemplateRepoWithoutData()
    {
        $result = new IdSearchResult(0,[],new Criteria(),Context::createDefaultContext());

        $this->repoMailTemplates->method('searchIds')->willReturn($result);
        $this->repoMailTemplates->expects($this->once())->method('searchIds');
    }

    private function setupMailTemplateRepoWithExistingData($id)
    {
        $data = [
            'primaryKey' => $id,
            'data' => [
                'id' => $id,
            ]
        ];
        $result = new IdSearchResult(1,[$data],new Criteria(),Context::createDefaultContext());
        $this->repoMailTemplates->method('searchIds')->willReturn($result);
        $this->repoMailTemplates->expects($this->once())->method('searchIds');
    }
}
