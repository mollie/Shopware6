<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\MailTemplate;

use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

class MailTemplateRepository implements MailTemplateRepositoryInterface
{
    /**
     * @var EntityRepository<MailTemplateCollection>
     */
    private $mailTemplateRepository;

    /**
     * @param EntityRepository<MailTemplateCollection> $mailTemplateRepository
     */
    public function __construct($mailTemplateRepository)
    {
        $this->mailTemplateRepository = $mailTemplateRepository;
    }

    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return IdSearchResult
     */
    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        return $this->mailTemplateRepository->searchIds($criteria, $context);
    }
}
